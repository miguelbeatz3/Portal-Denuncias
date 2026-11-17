# Portal de Denúncias de Luanda

Protótipo académico com uma interface pública Next.js e dois painéis Filament sobre a mesma aplicação Laravel. A configuração de referência usa MySQL. O teste de integração usa SQLite em memória.

## Executar localmente

São necessários PHP 8.4 com as extensões intl, mbstring e PDO para a base escolhida, Composer, Node.js 24 e npm. Execute os comandos a partir da raiz do repositório em PowerShell.

### API e painéis

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Copie o exemplo apenas na primeira instalação. Crie a base MySQL `portal_denuncias` e configure no ficheiro `.env` o utilizador e a palavra-passe com acesso a essa base. Para uma demonstração com SQLite, defina `DB_CONNECTION=sqlite`, remova a linha `DB_DATABASE` e crie o ficheiro vazio `database/database.sqlite` se ainda não existir.

```powershell
php artisan migrate
php artisan portal:demo
php artisan serve --host=127.0.0.1 --port=8000
```

O comando de demonstração apresenta as palavras-passe aleatórias das contas criadas. Guarde-as nesse momento. Chamadas posteriores preservam as contas existentes. O administrador entra em `/admin` e cada operador entra em `/entidade`.

### Portal público

Num segundo terminal:

```powershell
cd frontend
npm ci
npm run dev
```

Abra o portal em http://localhost:3000. O Next.js encaminha os pedidos públicos para http://127.0.0.1:8000. Para usar outro endereço, defina `PORTAL_API_URL` no ambiente do frontend antes de iniciar o servidor ou gerar o build.

## Demonstração

1. Registe uma ocorrência fictícia em `/registar` e guarde a referência e o código secreto.
2. Entre no painel administrativo com `admin@portal.test`, consulte a descrição e seleccione **Encaminhar**. Confirme a categoria e escolha Atendimento Municipal.
3. Entre em `/entidade` com `municipio@portal.test` e publique uma actualização. A opção **Concluir denúncia** permite encerrar o caso com uma mensagem final.
4. Abra `/acompanhar` no portal e consulte o estado e as mensagens com os dois dados do recibo.

## Verificação

```powershell
cd backend
php tests/workflow.php
cd ../frontend
npm run build
```

O teste valida registo anónimo e identificado, consulta com código, encaminhamento único, isolamento entre entidades, listagem Filament e histórico até à conclusão. Todos os registos do teste são temporários.
