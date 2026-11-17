<?php
use App\Models\{Category, Municipality, Report};
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

Route::get('/categories', function (Request $request) {
    $categories = Category::where('active', true)
        ->orderByRaw('CASE WHEN name = ? THEN 1 ELSE 0 END', ['Outro assunto'])->orderBy('name');
    return $request->boolean('details') ? $categories->get(['name', 'description']) : $categories->pluck('name');
});
Route::get('/municipalities', fn () => Municipality::where('active', true)->orderBy('name')->pluck('name'));

Route::post('/reports', function (Request $request) {
    if (is_string($request->input('phone'))) {
        $request->merge(['phone' => preg_replace('/[\s()-]+/', '', $request->input('phone'))]);
    }
    $data = $request->validate([
        'mode' => ['required', Rule::in(['anonymous', 'identified'])],
        'name' => ['exclude_unless:mode,identified', 'required', 'string', 'max:150'],
        'phone' => ['exclude_unless:mode,identified', 'nullable', 'string', 'regex:/^(?:[0-9]{9}|\+[1-9][0-9]{7,14})$/D'],
        'municipality' => ['required', 'string', 'max:100'],
        'occurred_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Africa/Luanda')->toDateString()],
        'category' => ['present', 'nullable', 'string', 'max:100'],
        'location' => ['required', 'string', 'max:200'],
        'description' => ['required', 'string', 'min:10', 'max:5000'],
        'has_evidence' => ['sometimes', 'boolean'],
        'evidence_description' => ['exclude_unless:has_evidence,1', 'nullable', 'string', 'max:5000'],
        'evidence_files' => ['exclude_unless:has_evidence,1', 'array', 'list', 'max:3'],
        'evidence_files.*' => ['exclude_unless:has_evidence,1', 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
    ]);
    $files = $data['evidence_files'] ?? [];
    if ($request->boolean('has_evidence') && trim($data['evidence_description'] ?? '') === '' && !$files) {
        throw ValidationException::withMessages(['evidence_description' => 'Descreva as provas ou anexe pelo menos um ficheiro.']);
    }
    unset($data['mode'], $data['has_evidence'], $data['evidence_files']);
    $secret = strtoupper(bin2hex(random_bytes(10)));
    $reference = 'LD-'.strtoupper(bin2hex(random_bytes(6)));
    $stored = [];
    try {
        DB::transaction(function () use ($data, $reference, $secret, $files, &$stored) {
            foreach (['category' => Category::class, 'municipality' => Municipality::class] as $field => $model) {
                if ($field === 'category' && $data[$field] === null) continue;
                $name = $model::where('active', true)->where('name', $data[$field])->sharedLock()->value('name');
                if ($name === null) {
                    throw ValidationException::withMessages([$field => 'Seleccione uma opção disponível.']);
                }
                $data[$field] = $name;
            }
            foreach ($files as $file) {
                $path = $file->store('report-evidence', 'local');
                if (!$path) throw new RuntimeException('Falha ao guardar o anexo.');
                $stored[] = $path;
            }
            $report = Report::create([...$data, 'evidence_files' => $stored, 'reference' => $reference, 'secret_hash' => hash('sha256', $secret)]);
            DB::table('report_events')->insert(['report_id' => $report->id, 'message' => 'Recebida para triagem.', 'created_at' => now()]);
        });
    } catch (Throwable $e) {
        if ($stored) Storage::disk('local')->delete($stored);
        throw $e;
    }
    return response()->json(compact('reference', 'secret'), 201)->header('Cache-Control', 'no-store');
})->middleware('throttle:10,1');

Route::post('/tracking', function (Request $request) {
    $credentials = $request->validate(['reference' => 'required|string|max:40', 'secret' => 'required|string|max:64']);
    $report = Report::where('reference', strtoupper($credentials['reference']))
        ->where('secret_hash', hash('sha256', strtoupper($credentials['secret'])))->first();
    abort_unless($report, 404, 'Verifique a referência e o código.');
    return response()->json([
        'reference' => $report->reference,
        'status' => $report->status,
        'description' => $report->description,
        'classified_as_false' => DB::table('report_events')->where('report_id', $report->id)
            ->whereNotNull('false_report_reason')->exists(),
        'events' => DB::table('report_events')->where('report_id', $report->id)->orderBy('id')->get(['message', 'created_at']),
    ])->header('Cache-Control', 'no-store');
})->middleware('throttle:30,1');
