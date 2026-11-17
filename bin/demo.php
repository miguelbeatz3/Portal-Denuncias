<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/model.php';
$db=database();
$db->beginTransaction();
if ($db->query('SELECT COUNT(*) FROM staff')->fetchColumn()) {
    exit("As contas já foram criadas. As credenciais existentes continuam válidas.\n");
}
$accounts=[];
foreach (['admin'=>null,'servicos'=>'Serviços Urbanos (simulação)','atendimento'=>'Atendimento Municipal (simulação)'] as $login=>$name) {
    $entity=null;
    if ($name) {
        query($db,'INSERT INTO entity(name) VALUES(?)',[$name]);
        $entity=(int)$db->lastInsertId();
    }
    $password=bin2hex(random_bytes(10));
    query($db,'INSERT INTO staff(login,hash,role,entity_id) VALUES(?,?,?,?)',
        [$login,password_hash($password,PASSWORD_DEFAULT),$entity?'entity':'admin',$entity]);
    $accounts[]="$login  $password";
}
$db->commit();
echo "Guarde as credenciais locais de demonstração:\n".implode("\n",$accounts)."\n";
