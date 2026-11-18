<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Models\Administrator;
use App\Models\Entity;
use App\Models\Municipality;

Artisan::command('portal:criar-administrador', function () {
    $name = trim((string) $this->ask('Nome do administrador'));
    $email = Str::lower(trim((string) $this->ask('E-mail do administrador')));
    $identity = Validator::make(compact('name', 'email'), [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
    ]);
    if ($identity->fails()) {
        $this->error($identity->errors()->first());
        return 1;
    }

    $password = $this->secret('Palavra-passe (mínimo de 12 caracteres)', false);
    if (mb_strlen((string) $password) < 12 || strlen((string) $password) > 72 || str_contains((string) $password, "\0")) {
        $this->error('A palavra-passe deve ter pelo menos 12 caracteres, até 72 bytes e sem bytes nulos.');
        return 1;
    }
    if ($password !== $this->secret('Confirme a palavra-passe', false)) {
        $this->error('As palavras-passe devem coincidir.');
        return 1;
    }

    Administrator::create($identity->validated() + ['password' => $password]);
    $this->info("Administrador criado para $email.");
})->purpose('Criar uma conta administrativa com palavra-passe introduzida no terminal.');

Artisan::command('portal:cadastrar-entidades-luanda', function () {
    $created = DB::transaction(function () {
        $municipalities = [
            'Belas' => 'de', 'Cacuaco' => 'de', 'Camama' => 'de', 'Cazenga' => 'do',
            'Hoji ya Henda' => 'do', 'Ingombota' => 'da', 'Kilamba' => 'do',
            'Kilamba Kiaxi' => 'do', 'Maianga' => 'da', 'Mulenvos' => 'dos',
            'Mussulo' => 'do', 'Rangel' => 'do', 'Samba' => 'da', 'Sambizanga' => 'do',
            'Talatona' => 'de', 'Viana' => 'de',
        ];
        $created = 0;
        foreach ($municipalities as $name => $preposition) {
            $municipality = Municipality::where('name', $name)->firstOrFail();
            foreach (['Administração Municipal', 'PNA — Comando Municipal'] as $prefix) {
                $entity = Entity::firstOrCreate(['name' => "$prefix $preposition $name"], [
                    'municipality_id' => $municipality->id,
                    'service_scope' => 'municipal',
                    'accepts_reports' => true,
                ]);
                $created += (int) $entity->wasRecentlyCreated;
            }
        }

        foreach ([
            'Polícia Nacional de Angola — Comando Provincial de Luanda',
            'Serviço de Investigação Criminal (SIC)',
            'Inspecção Geral da Administração do Estado (IGAE)',
            'Inspecção Geral do Trabalho (IGT)',
            'Autoridade Nacional de Inspecção Económica e Segurança Alimentar (ANIESA)',
            'Empresa Pública de Águas de Luanda (EPAL)',
            'Instituto Regulador dos Serviços de Electricidade e de Água (IRSEA)',
        ] as $name) {
            $entity = Entity::firstOrCreate(['name' => $name], [
                'service_scope' => 'general',
                'accepts_reports' => true,
            ]);
            $created += (int) $entity->wasRecentlyCreated;
        }

        return $created;
    });

    $this->info("Novas entidades cadastradas: $created.");
})->purpose('Cadastrar as entidades municipais e especializadas de Luanda.');

Artisan::command('portal:preencher-contactos-luanda', function () {
    $directory = require database_path('data/contactos_luanda.php');
    $updated = DB::transaction(function () use ($directory) {
        $updated = 0;
        foreach ($directory as $name => $fields) {
            $entity = Entity::where('name', $name)->lockForUpdate()->firstOrFail();
            foreach ($fields as $field => $value) {
                if (blank($entity->$field)) {
                    $entity->$field = $value;
                }
            }
            if ($entity->isDirty()) {
                $entity->saveOrFail();
                $updated++;
            }
        }
        return $updated;
    });
    $this->info("Fichas actualizadas: $updated.");
})->purpose('Preencher os contactos e moradas oficiais em falta nas entidades de Luanda.');

Artisan::command('portal:demo', function () {
    if (!app()->environment('local')) {
        $this->error('A demonstração requer APP_ENV=local.');
        return 1;
    }
    $accounts = DB::transaction(function () {
        $accounts = [];
        foreach (['admin' => null, 'municipio' => 'Atendimento Municipal', 'servicos' => 'Serviços Urbanos'] as $login => $entity) {
            $email = $login.'@portal.test';
            if (DB::table('users')->where('email', $email)->exists()) {
                continue;
            }
            $id = null;
            if ($entity) {
                DB::table('entities')->insertOrIgnore(['name' => $entity.' (demonstração)']);
                $id = DB::table('entities')->where('name', $entity.' (demonstração)')->value('id');
            }
            $password = Str::random(24);
            DB::table('users')->insert(['name' => $entity ?? 'Administrador', 'email' => $email,
                'password' => Hash::make($password), 'role' => $id ? 'entity' : 'admin', 'entity_id' => $id,
                'created_at' => now(), 'updated_at' => now()]);
            $accounts[] = [$email, $password];
        }
        return $accounts;
    });
    $this->table(['Conta local', 'Palavra-passe inicial'], $accounts);
});
