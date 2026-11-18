<?php
// Run from the backend with php tests/workflow.php. All records live in memory.
use App\Filament\ReportResource;
use App\Models\{Entity, Operator, Report, User};
use App\Services\ReportWorkflow;
use Filament\Facades\Filament;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Auth, DB};
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
    'cache.default' => 'array', 'session.driver' => 'array', 'app.key' => 'base64:'.base64_encode(random_bytes(32))]);
Artisan::call('migrate', ['--force' => true]);

function check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
function postJson(string $uri, array $data, int $expected): array
{
    global $kernel;
    $request = Request::create('/api/'.$uri, 'POST', server: ['HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json'], content: json_encode($data));
    $response = $kernel->handle($request);
    check($response->getStatusCode() === $expected, $uri.' returned '.$response->getStatusCode());
    return json_decode($response->getContent(), true);
}

$input = ['mode' => 'anonymous', 'name' => 'Ignorar', 'phone' => '+244 900 000 001', 'category' => 'Outro assunto', 'location' => 'Luanda',
    'municipality' => 'Ingombota', 'description' => 'Ocorrência fictícia de teste'];
$receipt = postJson('reports', $input, 201);
$report = Report::firstOrFail();
check($report->name === null, 'Anonymous name retained');
check($report->phone === null, 'Anonymous phone retained');
postJson('reports', array_merge($input, ['mode' => 'identified', 'name' => '']), 422);
$identifiedReceipt = postJson('reports', array_merge($input, [
    'mode' => 'identified', 'occurred_on' => '2025-08-10', 'municipality' => 'Viana',
]), 201);
$identifiedReport = Report::where('reference', $identifiedReceipt['reference'])->firstOrFail();
check($identifiedReport->phone === '+244900000001' && $identifiedReport->municipality === 'Viana'
    && $identifiedReport->occurred_on === '2025-08-10', 'Missing contact or occurrence data');
check(!array_key_exists('phone', postJson('tracking', $identifiedReceipt, 200)), 'Telephone in public tracking');
postJson('reports', array_merge($input, ['mode' => 'identified', 'phone' => 'invalid']), 422);
postJson('reports', array_merge($input, ['occurred_on' => '2099-01-01']), 422);
check(Report::latest('id')->first()->name === 'Ignorar', 'Identified name missing');
postJson('tracking', array_merge($receipt, ['secret' => 'INVALID']), 404);
$entity = DB::table('entities')->insertGetId(['name' => 'Entidade de teste']);
$other = DB::table('entities')->insertGetId(['name' => 'Outra entidade']);
foreach (['admin' => null, 'operador' => $entity, 'outro' => $other] as $name => $id) {
    $users[$name] = User::forceCreate(['name' => $name, 'email' => $name.'@example.test',
        'password' => 'unused', 'role' => $id ? 'entity' : 'admin', 'entity_id' => $id]);
}
$paused = Entity::create(['name' => 'Recepção suspensa', 'accepts_reports' => false]);
$municipalityId = DB::table('municipalities')->where('name', $report->municipality)->value('id');
$localEntity = Entity::create(['name' => 'Serviço municipal', 'service_scope' => 'municipal', 'municipality_id' => $municipalityId]);
$specialist = Entity::create(['name' => 'Serviço especializado', 'service_scope' => 'general', 'municipality_id' => $municipalityId]);
$outside = Entity::create(['name' => 'Serviço de Viana', 'service_scope' => 'municipal',
    'municipality_id' => DB::table('municipalities')->where('name', 'Viana')->value('id')]);
$destinations = Entity::destinations($report);
check(array_key_first($destinations) === 'Sugeridas para '.$report->municipality
    && isset($destinations[array_key_first($destinations)][$localEntity->id],
        $destinations['Entidades de âmbito geral ou especializado'][$specialist->id],
        $destinations['Outras entidades disponíveis'][$outside->id], $destinations['Outras entidades disponíveis'][$entity]),
    'Incomplete destination groups');
$redirectOptions = Entity::destinations($report->replicate()->forceFill(['entity_id' => $localEntity->id]));
foreach ($redirectOptions as $group) {
    check(!isset($group[$localEntity->id]) && !isset($group[$paused->id]), 'Ineligible destination suggested');
}
check(!Entity::receiving()->whereKey($paused->id)->exists(), 'Paused entity offered for assignment');
try {
    ReportWorkflow::assign($report, $users['admin'], ['category' => 'Outro assunto', 'entity_id' => $paused->id]);
    throw new RuntimeException('Paused entity accepted assignment');
} catch (\Illuminate\Validation\ValidationException) {}
check(!Operator::whereKey($users['admin']->id)->exists(), 'Administrator listed as an operator');
ReportWorkflow::assign($report, $users['admin'], ['category' => 'Vandalismo e danos a bens', 'entity_id' => $entity]);
check(postJson('tracking', $receipt, 200)['status'] === 'Encaminhada', 'Initial routing is not public');
Entity::findOrFail($entity)->update(['accepts_reports' => false]);
try {
    ReportWorkflow::assign($report, $users['admin'], ['category' => 'Outro assunto', 'entity_id' => $other]);
    throw new RuntimeException('Duplicate assignment accepted');
} catch (\Illuminate\Validation\ValidationException) {}
Filament::setCurrentPanel(Filament::getPanel('entidade'));
Auth::login($users['outro']);
check(!ReportResource::getEloquentQuery()->whereKey($report->id)->exists(), 'Report visible across entities');
try {
    ReportWorkflow::publish($report, $users['outro'], ['message' => 'indev']);
    throw new RuntimeException('Cross-entity publication accepted');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected denial'); }
foreach (['admin' => 'admin', 'operador' => 'entidade'] as $user => $panel) {
    // Each HTTP request gets its own Filament navigation manager.
    $app->forgetInstance(\Filament\Navigation\NavigationManager::class);
    Auth::login($users[$user]);
    check($users[$user]->canAccessPanel(Filament::getPanel($panel)), 'Panel denied');
    $response = $kernel->handle(Request::create('/'.$panel.'/denuncias'));
    check($response->isOk() && str_contains($response->getContent(), $report->reference), 'Filament list failed: '.$panel.' '.$response->getStatusCode());
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $menu = new DOMXPath($doc);
    $links = $menu->query('//nav[contains(@class, "fi-sidebar-nav")]//a[@href]');
    $labels = array_map(fn ($link) => trim($link->textContent), iterator_to_array($links));
    $expected = $panel === 'admin'
        ? ['Visão geral', 'Por triar', 'Todas as denúncias', 'Relatórios', 'Entidades', 'Operadores', 'Categorias', 'Municípios', 'Utilizadores']
        : ['Visão geral', 'Denúncias da entidade', 'Relatórios'];
    check($labels === $expected, 'Unexpected sidebar on '.$panel.': '.implode(', ', $labels));
    if ($panel === 'admin') {
        $adminLinks = $menu->query('//li[contains(concat(" ",normalize-space(@class)," "), " fi-sidebar-group ")][.//span[normalize-space(.)="Acessos administrativos"]]//a[@href]');
        check($adminLinks->length === 1 && trim($adminLinks->item(0)->textContent) === 'Utilizadores', 'Incorrect administrative access group');
    }
}
try {
    ReportWorkflow::publish($report, $users['operador'], ['message' => 'Publicação prematura']);
    throw new RuntimeException('Publication accepted before treatment');
} catch (\Illuminate\Validation\ValidationException) {}
ReportWorkflow::startTreatment($report, $users['operador']);
try {
    ReportWorkflow::startTreatment($report, $users['operador']);
    throw new RuntimeException('Treatment started twice');
} catch (\Illuminate\Validation\ValidationException) {}
ReportWorkflow::publish($report, $users['operador'], ['message' => 'Em análise.']);
ReportWorkflow::publish($report, $users['operador'], ['message' => 'Resolvida.', 'close' => true]);
$history = postJson('tracking', $receipt, 200);
check($history['description'] === $input['description'] && $history['classified_as_false'] === false, 'Incorrect citizen report details');
check($history['status'] === 'Concluída' && array_column($history['events'], 'message') ===
    ['Recebida para triagem.', 'Encaminhada para Entidade de teste.', 'A entidade iniciou o tratamento da denúncia.', 'Em análise.', 'Resolvida.'], 'Incorrect history');
Auth::login($users['admin']);
DB::table('report_events')->where('report_id', $report->id)->where('user_id', $users['admin']->id)
    ->update(['created_at' => '2025-12-31 23:30:00']);
$adminDetails = ReportResource::details($report)->render();
check(str_contains($adminDetails, '<dd>Ingombota</dd>')
    && str_contains($adminDetails, '<dd>Entidade de teste</dd>'), 'Incorrect consultation summary');
foreach (['Histórico administrativo', 'Triagem por admin', '01/01/2026 00:30',
    'Encaminhada para Entidade de teste.', 'Publicado por operador'] as $detail) {
    check(str_contains($adminDetails, $detail), 'Administrative history missing '.$detail);
}
check(substr_count($adminDetails, 'Publicado por operador') === 3, 'Each operator event needs an author');
Auth::login($users['operador']);
$entityDetails = ReportResource::details($report)->render();
check(str_contains($entityDetails, 'Histórico público') && !str_contains($entityDetails, 'Triagem por')
    && !str_contains($entityDetails, 'Publicado por'), 'Administrative authors exposed in entity panel');
foreach ($history['events'] as $event) {
    check(array_keys($event) === ['message', 'created_at'], 'Internal event data in citizen API');
}
Auth::login($users['admin']);
$home = $kernel->handle(Request::create('/admin'));
check($home->isOk() && str_contains($home->getContent(), 'Fila de triagem')
    && str_contains($home->getContent(), $identifiedReport->reference)
    && str_contains($home->getContent(), 'Nenhuma entidade tem denúncias em tratamento.'), 'Admin dashboard');

function tableRows(string $url): string
{
    global $kernel;
    $response = $kernel->handle(Request::create($url));
    check($response->isOk(), 'List failed: '.$url);
    $doc = new DOMDocument();
    @$doc->loadHTML($response->getContent());
    return implode("\n", array_map(fn ($row) => $row->textContent,
        iterator_to_array((new DOMXPath($doc))->query('//tbody/tr'))));
}

$entityRows = tableRows('/admin/entidades');
$manager = \App\Models\Administrator::create(['name' => 'Gestora de teste', 'email' => 'gestora@example.test', 'password' => 'palavra-passe-teste']);
$userRows = tableRows('/admin/utilizadores');
check(str_contains($userRows, $manager->email) && !str_contains($userRows, $users['operador']->email), 'Incorrect administrative user list');
check(password_verify('palavra-passe-teste', $manager->password), 'Administrator password not hashed');
try {
    $manager->setSuspended(true, $manager);
    throw new RuntimeException('Administrator suspended own account');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Incorrect self-suspension response'); }
$manager->setSuspended(true, $users['admin']);
check(!$manager->refresh()->canAccessPanel(Filament::getPanel('admin')), 'Suspended administrator retains access');
$manager->setSuspended(false, $users['admin']);
check($manager->refresh()->canAccessPanel(Filament::getPanel('admin')) && $manager->session_version === 1, 'Administrator reactivation failed');
$operatorPath = '/admin/operadores/'.$users['operador']->id;
check(str_contains($kernel->handle(Request::create('/admin/operadores'))->getContent(), $operatorPath), 'Missing operator detail link');
$operatorPage = $kernel->handle(Request::create($operatorPath));
check($operatorPage->isOk() && str_contains($operatorPage->getContent(), $users['operador']->email)
    && str_contains($operatorPage->getContent(), '/admin/entidades/'.$entity)
    && str_contains($operatorPage->getContent(), 'Suspender'), 'Missing operator details or actions');
check($kernel->handle(Request::create('/admin/operadores/'.$users['admin']->id))->getStatusCode() === 404, 'Administrator exposed as operator');
check(str_contains($entityRows, 'Activa') && str_contains($entityRows, 'Inactiva'), 'Missing entity status badges');
$localEntity->update(['address' => 'Rua de teste', 'phone' => '900000001', 'email' => 'contacto@example.test']);
$entityPath = '/admin/entidades/'.$localEntity->id;
check(str_contains($kernel->handle(Request::create('/admin/entidades'))->getContent(), $entityPath), 'Missing entity detail link');
$entityDetail = $kernel->handle(Request::create($entityPath));
check($entityDetail->isOk(), 'Entity detail page failed');
check(str_contains($entityDetail->getContent(), 'Desactivar entidade') && str_contains($entityDetail->getContent(), 'Editar'),
    'Missing actions on active entity page');
$inactivePage = $kernel->handle(Request::create('/admin/entidades/'.$paused->id));
check($inactivePage->isOk() && str_contains($inactivePage->getContent(), 'Activar entidade')
    && !str_contains($inactivePage->getContent(), 'Desactivar entidade'), 'Incorrect inactive entity actions');
foreach ([$localEntity->name, 'Activa', 'Municipal', $report->municipality, 'Rua de teste', '900000001', 'contacto@example.test'] as $value) {
    check(str_contains($entityDetail->getContent(), $value), 'Missing entity detail: '.$value);
}
$newer = Report::where('status', 'Recebida')->firstOrFail();
$assignedList = tableRows('/admin/entidades/'.$entity);
check(str_contains($assignedList, $report->reference) && !str_contains($assignedList, $newer->reference)
    && !str_contains(tableRows('/admin/entidades/'.$other), $report->reference), 'Incorrect reports on entity page');
$older = $newer->replicate();
$older->reference = 'LD-TEST-OLD';
$older->created_at = '2025-01-02 09:15:00';
$older->save();
$queue = tableRows('/admin/triagem');
$dashboard = $kernel->handle(Request::create('/admin'))->getContent();
$yearPage = $kernel->handle(Request::create('/admin?period=year'));
check($yearPage->isOk() && str_contains($yearPage->getContent(), now('Africa/Luanda')->startOfYear()->format('d/m/Y').' – '.now('Africa/Luanda')->format('d/m/Y'))
    && str_contains($yearPage->getContent(), '<polyline') && str_contains($yearPage->getContent(), 'janeiro'), 'Dashboard yearly line chart');
check(!str_contains($dashboard, $older->reference) && str_contains($dashboard, $newer->reference)
    && str_contains($dashboard, 'Denúncias recebidas'), 'Dashboard default range');
$historical = $kernel->handle(Request::create('/admin?period=custom&from=2025-01-02&until=2025-01-02'));
check($historical->isOk() && str_contains($historical->getContent(), $older->reference)
    && !str_contains($historical->getContent(), $newer->reference), 'Dashboard custom range');
$datedQueue = tableRows('/admin/triagem?'.http_build_query(['filters' => ['received' => ['from' => '2025-01-02', 'until' => '2025-01-02']]]));
$reportParams = '?from=2025-01-02&until=2025-01-02&group=entity&municipality=Viana';
$reportPage = $kernel->handle(Request::create('/admin/relatorios'.$reportParams));
check($reportPage->isOk() && str_contains($reportPage->getContent(), 'Por atribuir')
    && str_contains($reportPage->getContent(), 'Exportar CSV'), 'Missing administrative report results');
$csv = $kernel->handle(Request::create('/admin/relatorios/exportar'.$reportParams));
check($csv instanceof \Symfony\Component\HttpFoundation\StreamedResponse, 'Missing CSV response');
ob_start();
$csv->sendContent();
$content = ob_get_clean();
check(str_contains($content, 'Total;1;0;0;0;0;0;0;1'), 'Incorrect filtered CSV counts');
$pdfReport = $kernel->handle(Request::create('/admin/relatorios/exportar/pdf'.$reportParams));
check($pdfReport->isOk() && $pdfReport->headers->get('Content-Type') === 'application/pdf'
    && str_starts_with($pdfReport->getContent(), '%PDF-') && str_contains($pdfReport->headers->get('Content-Disposition'), '.pdf'),
    'Missing filtered PDF download');
check(str_contains($datedQueue, $older->reference) && !str_contains($datedQueue, $newer->reference), 'Dashboard triage date link');
$detailPath = '/admin/denuncias/'.$older->getKey();
foreach (['/admin/triagem', '/admin/denuncias'] as $listPath) {
    check(str_contains($kernel->handle(Request::create($listPath))->getContent(), $detailPath), 'Missing report page link');
}
$detailPage = $kernel->handle(Request::create($detailPath));
check($detailPage->isOk() && str_contains($detailPage->getContent(), $older->reference)
    && str_contains($detailPage->getContent(), 'Detalhes da ocorrência')
    && str_contains($detailPage->getContent(), 'Encaminhar'), 'Missing report page details or action');
check(str_contains($queue, 'Viana'), 'Municipality missing in triage');
check(str_contains($queue, '02/01/2025 10:15'), 'Missing Luanda reception time');
check(str_contains($queue, $newer->reference) && str_contains($queue, $older->reference)
    && strpos($queue, $older->reference) < strpos($queue, $newer->reference), 'Queue order');
check(!str_contains($queue, $report->reference), 'Processed report in queue');

foreach ([[$entity, 'Outro assunto'], [$other, 'Vandalismo e danos a bens']] as [$assignedEntity, $category]) {
    $different = $report->fresh()->replicate();
    $different->fill(['reference' => 'LD-DIFF-'.$assignedEntity, 'entity_id' => $assignedEntity, 'category' => $category])->save();
}
$filters = ['entity_id' => ['value' => $entity], 'category' => ['value' => 'Vandalismo e danos a bens']];
$filtered = tableRows('/admin/denuncias?'.http_build_query(['filters' => $filters]));
check(str_contains($filtered, 'Ingombota'), 'Municipality missing in general list');
check(str_contains($filtered, $report->reference) && !str_contains($filtered, 'LD-DIFF-')
    && !str_contains($filtered, $older->reference), 'Combined entity and category filters');
$correctionReceipt = postJson('reports', $input, 201);
$correction = Report::where('reference', $correctionReceipt['reference'])->firstOrFail();
ReportWorkflow::sendMessage($correction, $users['admin'], 'Pode indicar a hora em que ocorreu?');
$citizenReply = $correctionReceipt + ['message' => 'Ocorreu pelas 10h.', 'message_id' => (string) \Illuminate\Support\Str::uuid()];
postJson('tracking/messages', array_replace($citizenReply, ['secret' => $receipt['secret']]), 404);
postJson('tracking/messages', array_replace($citizenReply, ['message' => '  ']), 422);
$sent = postJson('tracking/messages', $citizenReply, 201);
check($sent['sender'] === 'citizen' && $sent['message'] === $citizenReply['message']
    && array_keys($sent) === ['id', 'sender', 'message', 'created_at'], 'Incorrect citizen message or leaked metadata');
check(postJson('tracking/messages', $citizenReply, 201) === $sent, 'Retry did not return the original message');
postJson('tracking/messages', array_replace($citizenReply, ['message' => 'Outro texto com o mesmo identificador.']), 409);
check(postJson('tracking', $receipt, 200)['messages'] === [], 'Conversation leaked to another report');
Entity::findOrFail($entity)->update(['accepts_reports' => true]);
ReportWorkflow::assign($correction, $users['admin'], ['category' => 'Outro assunto', 'entity_id' => $entity]);
ReportWorkflow::sendMessage($correction, $users['operador'], 'Recebemos a sua resposta.');
try {
    ReportWorkflow::sendMessage($correction, $users['outro'], 'Mensagem de outra entidade.');
    throw new RuntimeException('Cross-entity message accepted');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected message denial'); }
$conversation = postJson('tracking', $correctionReceipt, 200);
check($conversation['status'] === 'Encaminhada' && array_column($conversation['messages'], 'sender') === ['admin', 'citizen', 'entity']
    && array_column($conversation['messages'], 'message') === ['Pode indicar a hora em que ocorreu?', $citizenReply['message'], 'Recebemos a sua resposta.'],
    'Conversation order or report state changed');
$dashboard = $kernel->handle(Request::create('/admin'));
check($dashboard->isOk() && str_contains($dashboard->getContent(), 'Encaminhada')
    && str_contains($dashboard->getContent(), 'Nenhuma entidade tem denúncias em tratamento.'), 'Routing counts toward treatment workload');
$openRecord = $correction->fresh();
foreach (['operador' => true, 'outro' => false] as $login => $ownsReport) {
    Auth::login($users[$login]);
    $overview = $kernel->handle(Request::create('/entidade'));
    $html = $overview->getContent();
    check($overview->isOk() && str_contains($html, 'Denúncias em aberto')
        && str_contains($html, $correction->reference) === $ownsReport
        && !str_contains($html, 'Fila de triagem'), 'Entity overview scope or navigation incorrect');
    $expectedTotal = Report::where('entity_id', $users[$login]->entity_id)
        ->whereBetween('created_at', [now('Africa/Luanda')->startOfDay()->subDays(29)->utc(), now('Africa/Luanda')->endOfDay()->utc()])->count();
    check(str_contains($html, "<strong>{$expectedTotal}</strong> no período"), 'Entity chart includes incorrect reports');
    $ownName = $ownsReport ? 'Entidade de teste' : 'Outra entidade';
    $otherName = $ownsReport ? 'Outra entidade' : 'Entidade de teste';
    $params = '?group=entity&entity='.($ownsReport ? $other : $entity);
    $page = $kernel->handle(Request::create('/entidade/relatorios'.$params));
    check($page->isOk() && str_contains($page->getContent(), $ownName)
        && !str_contains($page->getContent(), $otherName) && !str_contains($page->getContent(), 'Denúncias falsas')
        && str_contains($page->getContent(), '/entidade/relatorios/exportar'), 'Entity report scope or links incorrect');
    $csv = $kernel->handle(Request::create('/entidade/relatorios/exportar'.$params));
    ob_start();
    $csv->sendContent();
    $output = ob_get_clean();
    check(str_contains($output, $ownName) && !str_contains($output, $otherName)
        && !str_contains($output, 'Denúncias falsas') && str_ends_with(trim($output), ';'.$expectedTotal), 'Incorrect entity CSV');
    $pdf = $kernel->handle(Request::create('/entidade/relatorios/exportar/pdf'.$params));
    check($pdf->isOk() && str_starts_with($pdf->getContent(), '%PDF-'), 'Entity PDF missing');
}
Auth::login($users['admin']);
function checkTableBadgeColors(array $expected): void
{
    global $kernel;
    $response = $kernel->handle(Request::create('/admin/denuncias'));
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($doc);
    foreach ($expected as $label => $color) {
        // Gray is Filament's unmodified badge style.
        $colorClass = $color === 'gray' ? "not(contains(@class, 'fi-color-'))" : "contains(@class, 'fi-color-$color')";
        $matches = $xpath->query("//tbody//*[contains(concat(' ', @class, ' '), ' fi-badge ') and $colorClass and normalize-space(.) = '$label']");
        check($matches->length > 0, 'Incorrect table badge: '.$label);
    }
}
checkTableBadgeColors(['Recebida' => 'warning', 'Encaminhada' => 'primary', 'Concluída' => 'success']);
$transfer = ['previous_entity_id' => $entity, 'entity_id' => $other, 'reason' => 'Competência territorial revista.'];
$entityDetailPath = '/entidade/denuncias/'.$correction->getKey();
Auth::login($users['operador']);
$awaiting = $kernel->handle(Request::create($entityDetailPath));
check($awaiting->isOk() && str_contains($awaiting->getContent(), 'Iniciar tratamento')
    && str_contains($awaiting->getContent(), 'Solicitar reencaminhamento')
    && str_contains($awaiting->getContent(), 'Enviar mensagem') && str_contains($awaiting->getContent(), $citizenReply['message'])
    && !str_contains($awaiting->getContent(), 'Publicar actualização'), 'Wrong entity actions before treatment');
$request = ['suggested_entity_id' => $other, 'reason' => 'Pedido interno da entidade.'];
try {
    ReportWorkflow::requestReassignment($correction, $users['outro'], $request);
    throw new RuntimeException('Cross-entity request accepted');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected request denial'); }
ReportWorkflow::requestReassignment($correction, $users['operador'], $request);
$firstRequest = $correction->pendingReassignmentRequest()->firstOrFail();
try {
    ReportWorkflow::requestReassignment($correction, $users['operador'], $request);
    throw new RuntimeException('Duplicate pending request accepted');
} catch (\Illuminate\Validation\ValidationException) {}
$stillAssigned = postJson('tracking', $correctionReceipt, 200);
check($stillAssigned['status'] === 'Encaminhada' && $correction->fresh()->entity_id === $entity
    && !str_contains(json_encode($stillAssigned), $request['reason']), 'Private request changed public state');
Auth::login($users['admin']);
$pendingPage = $kernel->handle(Request::create('/admin/denuncias/'.$correction->getKey()));
check($pendingPage->isOk() && str_contains($pendingPage->getContent(), 'Aprovar e reencaminhar')
    && str_contains($pendingPage->getContent(), 'Recusar pedido')
    && str_contains($pendingPage->getContent(), 'Enviar mensagem') && str_contains($pendingPage->getContent(), $citizenReply['message'])
    && str_contains($pendingPage->getContent(), $request['reason']), 'Pending request missing from administrative detail');
$pendingRows = tableRows('/admin/denuncias?'.http_build_query(['filters' => ['pending_reassignment' => ['isActive' => 1]]]));
check(str_contains($pendingRows, $correction->reference) && !str_contains($pendingRows, $newer->reference), 'Pending request filter failed');
ReportWorkflow::declineReassignment($correction, $users['admin'], $firstRequest->id, 'Fundamentação insuficiente.');
Auth::login($users['operador']);
check(str_contains(ReportResource::details($correction)->render(), 'Fundamentação insuficiente.'), 'Request response missing for requesting entity');
ReportWorkflow::requestReassignment($correction, $users['operador'], $request);
$transfer['request_id'] = $correction->pendingReassignmentRequest()->value('id');
Auth::login($users['admin']);
foreach ([['entity_id' => $entity], ['entity_id' => $paused->id], ['reason' => '  '], ['previous_entity_id' => $other], ['request_id' => $firstRequest->id]] as $invalid) {
    try {
        ReportWorkflow::reassign($openRecord, $users['admin'], array_replace($transfer, $invalid));
        throw new RuntimeException('Invalid reassignment accepted');
    } catch (\Illuminate\Validation\ValidationException) {}
}
try {
    ReportWorkflow::reassign($openRecord, $users['operador'], $transfer);
    throw new RuntimeException('Operator reassigned report');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected reassignment denial'); }
ReportWorkflow::reassign($openRecord, $users['admin'], $transfer);
check($correction->reassignmentRequests()->findOrFail($transfer['request_id'])->resolution === 'Reencaminhada'
    && postJson('tracking', $correctionReceipt, 200)['status'] === 'Reencaminhada', 'Accepted request or public routing status not updated');
checkTableBadgeColors(['Reencaminhada' => 'primary']);
$details = ReportResource::details($correction)->render();
foreach (['Reencaminhamento por admin', 'Entidade anterior: Entidade de teste', 'Reencaminhada para Outra entidade.', $transfer['reason']] as $value) {
    check(str_contains($details, $value), 'Missing administrative correction detail');
}
Auth::login($users['operador']);
check(!ReportResource::getEloquentQuery()->whereKey($correction->id)->exists(), 'Old entity can list report');
check($kernel->handle(Request::create($entityDetailPath))->getStatusCode() === 404, 'Former entity can open direct page');
try {
    ReportResource::details($openRecord);
    throw new RuntimeException('Old entity can reopen report');
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {}
try {
    ReportWorkflow::publish($openRecord, $users['operador'], ['message' => 'Pedido anterior']);
    throw new RuntimeException('Stale publication accepted after transfer');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected stale publication response'); }
try {
    ReportWorkflow::sendMessage($openRecord, $users['operador'], 'Mensagem após transferência.');
    throw new RuntimeException('Former entity sent a message');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected stale message response'); }
Auth::login($users['outro']);
$entityPage = $kernel->handle(Request::create($entityDetailPath));
check($entityPage->isOk() && str_contains($entityPage->getContent(), 'Iniciar tratamento')
    && str_contains($entityPage->getContent(), 'Solicitar reencaminhamento')
    && str_contains($entityPage->getContent(), $citizenReply['message'])
    && !str_contains($entityPage->getContent(), $request['reason'])
    && !str_contains($entityPage->getContent(), $transfer['reason']), 'Incorrect entity detail page');
check(!str_contains(ReportResource::details($correction)->render(), $transfer['reason']), 'Private reason in entity panel');
ReportWorkflow::startTreatment($correction, $users['outro']);
$inTreatment = $kernel->handle(Request::create($entityDetailPath));
check($inTreatment->isOk() && str_contains($inTreatment->getContent(), 'Publicar actualização')
    && !str_contains($inTreatment->getContent(), 'Iniciar tratamento'), 'Wrong entity actions during treatment');
ReportWorkflow::publish($correction, $users['outro'], ['message' => 'Seguimento pela nova entidade.']);
$public = postJson('tracking', $correctionReceipt, 200);
check($public['status'] === 'Em tratamento' && array_column($public['events'], 'message') === [
    'Recebida para triagem.', 'Encaminhada para Entidade de teste.', 'Reencaminhada para Outra entidade.',
    'A entidade iniciou o tratamento da denúncia.', 'Seguimento pela nova entidade.',
], 'Incorrect public transfer history');
check(!str_contains(json_encode($public, JSON_UNESCAPED_UNICODE), $transfer['reason']), 'Private reason in public tracking');
Auth::login($users['admin']);
foreach (['municipios' => 'Ingombota', 'categorias' => 'Corrupção e abuso de poder'] as $slug => $name) {
    check(str_contains(tableRows('/admin/'.$slug), $name), 'Missing administrative catalogue: '.$slug);
}
$operator = Operator::findOrFail($users['outro']->id);
try {
    $operator->suspend($users['outro']);
    throw new RuntimeException('Operator suspended an account');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected suspension denial'); }
$operator->suspend($users['admin']);
$operator->refresh();
check(!$operator->canAccessPanel(Filament::getPanel('entidade')), 'Suspended operator can log in');
try {
    ReportWorkflow::publish($correction, $users['outro'], ['message' => 'Operação pendente']);
    throw new RuntimeException('Suspended operator published');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected publication denial'); }
$audit = ReportResource::details($correction)->render();
check(str_contains($audit, 'Publicado por outro') && str_contains($audit, 'Seguimento pela nova entidade.'),
    'Suspension removed attribution');
$operator->reactivate($users['admin']);
$operator->refresh();
check($operator->canAccessPanel(Filament::getPanel('entidade')) && $operator->session_version > 0,
    'Reactivation must require a new session');
$reason = 'Verificação administrativa de teste.';
try {
    ReportWorkflow::classifyAsFalse($correction, $users['outro'], $reason);
    throw new RuntimeException('Operator classified a report as false');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected classification denial'); }
try {
    ReportWorkflow::classifyAsFalse($correction, $users['admin'], '   ');
    throw new RuntimeException('Classification without reason');
} catch (\Illuminate\Validation\ValidationException) {}
ReportWorkflow::classifyAsFalse($correction, $users['admin'], $reason);
try {
    ReportWorkflow::classifyAsFalse($correction, $users['admin'], $reason);
    throw new RuntimeException('Repeated classification accepted');
} catch (\Illuminate\Validation\ValidationException) {}
$audit = ReportResource::details($correction)->render();
check(str_contains($audit, 'Denúncia Falsa') && str_contains($audit, 'Classificada por admin')
    && str_contains($audit, $reason), 'Missing classification audit');
$classifiedRecord = ReportResource::getEloquentQuery()->findOrFail($correction->id);
$heading = (new \App\Filament\ReportHeading($classifiedRecord))->toHtml();
check(str_contains($heading, 'fi-color-danger') && str_contains($heading, 'Arquivada') && strpos($heading, 'Arquivada') < strpos($heading, 'Denúncia Falsa'),
    'Missing red classification badge after status');
check(str_contains($audit, 'Motivo da classificação') && strpos($audit, $reason) < strpos($audit, 'Detalhes da ocorrência'),
    'Classification reason missing at top of modal');
$result = postJson('tracking', $correctionReceipt, 200);
check(count($result['messages']) === 3 && !str_contains(json_encode($result['messages']), $request['reason']), 'Internal text leaked into conversation');
check($result['classified_as_false'] === true, 'Missing public false classification');
check($result['status'] === 'Arquivada' && end($result['events'])['message'] === 'Denúncia arquivada.'
    && !str_contains(json_encode($result, JSON_UNESCAPED_UNICODE), $reason), 'Incorrect public closure');
$falseFilter = '?'.http_build_query(['filters' => ['classified_as_false' => ['isActive' => 1]]]);
$filteredRows = tableRows('/admin/denuncias'.$falseFilter);
checkTableBadgeColors(['Arquivada' => 'gray', 'Denúncia Falsa' => 'danger']);
check(str_contains($filteredRows, $correction->reference) && str_contains($filteredRows, 'Denúncia Falsa')
    && !str_contains($filteredRows, $report->reference) && !str_contains($filteredRows, $older->reference),
    'False classification badge or filter incorrect');
Auth::login($users['outro']);
$entityRows = tableRows('/entidade/denuncias'.$falseFilter);
check(str_contains($entityRows, $correction->reference) && !str_contains($entityRows, 'Denúncia Falsa')
    && str_contains($entityRows, 'LD-DIFF-'.$other), 'Administrative classification exposed to operator');
$entityView = ReportResource::details($correction)->render();
check(!str_contains((new \App\Filament\ReportHeading($classifiedRecord))->toHtml(), 'Denúncia Falsa'),
    'Classification badge visible to operator');
check(!str_contains($entityView, $reason) && !str_contains($entityView, 'Denúncia Falsa'), 'Private classification in entity panel');
try {
    ReportWorkflow::archive($report, $users['outro'], 'Pedido de teste');
    throw new RuntimeException('Operator archived a report');
} catch (HttpException $e) { check($e->getStatusCode() === 403, 'Unexpected archive denial'); }
try {
    ReportWorkflow::archive($report, $users['admin'], ' ');
    throw new RuntimeException('Archive without reason');
} catch (\Illuminate\Validation\ValidationException) {}
check($report->fresh()->status === 'Concluída', 'Ordinary closure changed');
ReportWorkflow::archive($report, $users['admin'], 'Processo revisto e arquivado.');
Auth::login($users['admin']);
$archiveReport = \App\Services\Reporting::generate(Request::create('/admin/relatorios'));
check($archiveReport['falseTotal'] === 1 && $archiveReport['totals']['Arquivada'] === 2
    && array_sum($archiveReport['totals']) === Report::whereBetween('created_at', [
        now('Africa/Luanda')->subDays(29)->startOfDay()->utc(), now('Africa/Luanda')->endOfDay()->utc(),
    ])->count(), 'False classification counted twice in report');
Auth::login($users['admin']);
$archiveDetails = ReportResource::details($report)->render();
check(str_contains($archiveDetails, 'Arquivada por admin') && str_contains($archiveDetails, 'Processo revisto e arquivado.')
    && !str_contains($archiveDetails, 'Denúncia Falsa'), 'Incorrect manual archive history');
$archivedHistory = postJson('tracking', $receipt, 200);
check($archivedHistory['classified_as_false'] === false, 'Ordinary archive marked as false');
check($archivedHistory['status'] === 'Arquivada' && !str_contains(json_encode($archivedHistory), 'Processo revisto'),
    'Incorrect public archive history');
$municipalities = $kernel->handle(Request::create('/api/municipalities'))->getData(true);

check(count($municipalities) === 16 && in_array('Hoji ya Henda', $municipalities, true), 'Incorrect Luanda catalogue');
postJson('reports', array_diff_key($input, ['municipality' => true]), 422);
postJson('reports', array_replace($input, ['municipality' => 'Município inexistente']), 422);
DB::table('municipalities')->where('name', 'Ingombota')->update(['active' => false]);
postJson('reports', $input, 422);
DB::table('categories')->where('name', 'Outro assunto')->update(['active' => false]);
postJson('reports', array_replace($input, ['municipality' => 'Belas']), 422);
$disk = \Illuminate\Support\Facades\Storage::fake('local');
Auth::logout();
$evidenceRequest = function (array $fields, array $files = []) use ($kernel, $input) {
    return $kernel->handle(Request::create('/api/reports', 'POST', [...$input, 'category' => 'Vandalismo e danos a bens', 'municipality' => 'Belas', 'has_evidence' => '1', ...$fields],
        files: $files ? ['evidence_files' => $files] : [],
        server: ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '127.0.0.42']));
};
check($evidenceRequest([])->getStatusCode() === 422, 'Missing evidence accepted');
check($evidenceRequest(['evidence_description' => 'Fotografia do local'])->getStatusCode() === 201, 'Text evidence rejected');
foreach ([[], ['evidence_description' => 'Comprovativo enviado']] as $fields) {
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('documento.pdf', "%PDF-1.4\n%%EOF");
    $response = $evidenceRequest($fields, [$file]);
    check($response->getStatusCode() === 201, 'File evidence rejected: '.$response->getContent());
    $saved = Report::where('reference', $response->getData(true)['reference'])->firstOrFail();
    check($saved->evidence_description === ($fields['evidence_description'] ?? null)
        && $disk->get($saved->evidence_files[0]) === "%PDF-1.4\n%%EOF", 'Evidence missing after submission');
}
$plainText = tmpfile();
fwrite($plainText, 'plain text');
fflush($plainText);
$invalidFile = new \Illuminate\Http\UploadedFile(stream_get_meta_data($plainText)['uri'], 'documento.pdf', 'application/pdf', null, true);
check($evidenceRequest([], [$invalidFile])->getStatusCode() === 422,
    'Unsupported file content accepted');
fclose($plainText);
$unclassified = $evidenceRequest(['has_evidence' => '0', 'category' => '']);
check($unclassified->getStatusCode() === 201, 'Cannot register a report without a known category');
$pending = Report::where('reference', $unclassified->getData(true)['reference'])->firstOrFail();
check($pending->category === null && $pending->status === 'Recebida', 'Incorrect pending classification');
try {
    ReportWorkflow::assign($pending, $users['admin'], ['entity_id' => $localEntity->id]);
    throw new RuntimeException('Report forwarded without classification');
} catch (\Illuminate\Validation\ValidationException $e) {
    check(isset($e->errors()['category']), 'Unexpected classification error');
}
ReportWorkflow::assign($pending, $users['admin'], ['entity_id' => $localEntity->id, 'category' => 'Vandalismo e danos a bens']);
check($pending->refresh()->category === 'Vandalismo e danos a bens' && $pending->status === 'Encaminhada', 'Triage classification not saved');
$saved->update(['entity_id' => $entity]);
$disk->put('report-evidence/test.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWZkAAAAASUVORK5CYII='));
$saved->update(['evidence_files' => [...$saved->evidence_files, 'report-evidence/test.png']]);
$detailPath = '/denuncias/'.$saved->getKey();
Auth::login($users['admin']);
$page = $kernel->handle(Request::create('/admin'.$detailPath));
check($page->isOk() && str_contains($page->getContent(), $detailPath.'/provas/0')
    && str_contains($page->getContent(), $detailPath.'/provas/1'), 'Evidence previews missing');
foreach (['application/pdf', 'image/png'] as $index => $mime) {
    $preview = $kernel->handle(Request::create('/admin'.$detailPath.'/provas/'.$index));
    check($preview->isOk() && $preview->headers->get('Content-Type') === $mime
        && str_starts_with($preview->headers->get('Content-Disposition') ?? '', 'inline;'), 'Incorrect inline evidence response');
}
Auth::login($users['operador']);
check($kernel->handle(Request::create('/entidade'.$detailPath.'/provas/0'))->isOk(), 'Assigned entity cannot preview evidence');
$saved->update(['entity_id' => $other]);
check($kernel->handle(Request::create('/entidade'.$detailPath.'/provas/0'))->getStatusCode() === 404, 'Evidence visible after reassignment');
Auth::login($users['admin']);
$pending->update(['entity_id' => $entity]);
$responsible = Operator::findOrFail($users['operador']->id);
ReportWorkflow::assignOperator($pending->id, $responsible, $users['admin']);
check(str_contains(tableRows('/admin/operadores/'.$responsible->id), $pending->reference)
    && !str_contains(tableRows('/admin/operadores/'.$responsible->id), $report->reference), 'Incorrect personal assignments');
ReportWorkflow::reassign($pending, $users['admin'], ['entity_id' => $other, 'previous_entity_id' => $entity, 'reason' => 'Transferência de teste']);
check($pending->refresh()->operator_id === null && !$responsible->reports()->whereKey($pending->id)->exists(), 'Operator retained transferred report');

$migration = require dirname(__DIR__).'/database/migrations/2026_11_18_000001_add_report_routing_workflow.php';
$migration->down();
foreach (['routed' => 'Encaminhada', 'rerouted' => 'Reencaminhada', 'treated' => 'Em tratamento', 'unknown' => 'Em tratamento'] as $case => $expected) {
    $legacy = $pending->replicate()->forceFill(['reference' => 'LD-LEGACY-'.$case, 'status' => 'Em tratamento']);
    $legacy->save();
    $event = ['report_id' => $legacy->id, 'created_at' => now()];
    if ($case !== 'unknown') {
        DB::table('report_events')->insert($event + ['user_id' => $users['admin']->id, 'message' => 'Encaminhada para Entidade de teste.']);
    }
    if (in_array($case, ['rerouted', 'treated'], true)) {
        DB::table('report_events')->insert($event + ['user_id' => $users['operador']->id, 'message' => 'Primeira entidade iniciou análise.']);
        DB::table('report_events')->insert($event + ['user_id' => $users['admin']->id, 'message' => 'Reencaminhada para Outra entidade.',
            'reassignment_reason' => 'Mudança territorial.']);
    }
    if ($case === 'treated') {
        DB::table('report_events')->insert($event + ['user_id' => $users['outro']->id, 'message' => 'Nova entidade em análise.']);
    }
    $legacyStatuses[$legacy->id] = $expected;
}
$migration->up();
foreach ($legacyStatuses as $id => $expected) {
    check(Report::findOrFail($id)->status === $expected, 'Incorrect legacy routing migration for '.$id);
}
echo "OK: registo, painéis, encaminhamento, pedidos, mensagens, isolamento, tratamento, conclusão e migração.\n";
