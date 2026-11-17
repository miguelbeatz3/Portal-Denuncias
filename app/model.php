<?php
declare(strict_types=1);

function database(): PDO {
    $dir = getenv('PORTAL_DATA_DIR') ?: dirname(__DIR__) . '/var';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de dados.');
    }
    $db = new PDO('sqlite:' . $dir . '/portal.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec("PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;
        CREATE TABLE IF NOT EXISTS entity (id INTEGER PRIMARY KEY, name TEXT UNIQUE NOT NULL);
        CREATE TABLE IF NOT EXISTS staff (id INTEGER PRIMARY KEY, login TEXT UNIQUE NOT NULL, hash TEXT NOT NULL,
            entity_id INTEGER REFERENCES entity(id), role TEXT NOT NULL CHECK(role IN ('admin','entity')),
            CHECK ((role='admin' AND entity_id IS NULL) OR (role='entity' AND entity_id IS NOT NULL)));
        CREATE TABLE IF NOT EXISTS report (id INTEGER PRIMARY KEY, ref TEXT UNIQUE NOT NULL, secret_hash TEXT NOT NULL,
            name TEXT, category TEXT NOT NULL, location TEXT NOT NULL, description TEXT NOT NULL,
            state TEXT NOT NULL DEFAULT 'Recebida', entity_id INTEGER REFERENCES entity(id));
        CREATE TABLE IF NOT EXISTS event (id INTEGER PRIMARY KEY, report_id INTEGER NOT NULL REFERENCES report(id),
            staff_id INTEGER REFERENCES staff(id), message TEXT NOT NULL, created TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);");
    return $db;
}
function query(PDO $db, string $sql, array $args=[]): PDOStatement {
    $q=$db->prepare($sql);
    $q->execute($args);
    return $q;
}
function recordEvent(PDO $db, int $id, ?int $actor, string $message): void {
    query($db,'INSERT INTO event(report_id,staff_id,message) VALUES(?,?,?)',[$id,$actor,$message]);
}
function registerReport(PDO $db, ?string $name, string $category, string $location, string $description): array {
    $ref='LD-'.strtoupper(bin2hex(random_bytes(5)));
    $code=strtoupper(bin2hex(random_bytes(8)));
    $db->beginTransaction();
    query($db,'INSERT INTO report(ref,secret_hash,name,category,location,description) VALUES(?,?,?,?,?,?)',
        [$ref,hash('sha256',$code),$name,$category,$location,$description]);
    recordEvent($db,(int)$db->lastInsertId(),null,'Denúncia recebida para triagem.');
    $db->commit();
    return [$ref,$code];
}
function advanceReport(PDO $db, array $user, int $id, string $action, int $destination, string $outcome): void {
    $db->beginTransaction();
    if ($action==='assign' && $user['role']==='admin') {
        $entity=query($db,'SELECT name FROM entity WHERE id=?',[$destination])->fetchColumn();
        if (!$entity) throw new DomainException('Seleccione uma entidade válida.');
        $q=query($db,"UPDATE report SET entity_id=?,state='Em tratamento' WHERE id=? AND state='Recebida'",[$destination,$id]);
        $message='Encaminhada para '.$entity.'.';
    } elseif ($action==='close' && $user['role']==='entity') {
        $q=query($db,"UPDATE report SET state='Concluída' WHERE id=? AND entity_id=? AND state='Em tratamento'",[$id,$user['entity_id']]);
        $message='Conclusão: '.$outcome;
    } else {
        throw new DomainException('Esta operação requer outro perfil de acesso.');
    }
    if ($q->rowCount()!==1) throw new DomainException('A denúncia já mudou de estado ou está fora da sua área.');
    recordEvent($db,$id,$user['id'],$message);
    $db->commit();
}
