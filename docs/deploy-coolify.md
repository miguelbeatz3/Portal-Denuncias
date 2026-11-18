# Publicar o Portal de Denúncias no Coolify

Crie dois recursos no Coolify. A aplicação Docker Compose contém o `frontend`, que serve o portal do cidadão, e o `backend`, que serve a API e os dois painéis Laravel. O recurso MySQL tem volume e ciclo de deploy próprios. O frontend encaminha os pedidos `/api/...` para `http://backend` pela rede do Compose. O backend liga-se ao MySQL pela rede privada do destino Coolify.

## 1. Preparar o Git e o DNS

1. Publique no GitHub a revisão que inclui `docker-compose.yml`, os Dockerfiles e a configuração dos painéis. O Coolify faz o checkout da branch remota.
2. Confirme que o servidor de destino está ligado ao Coolify e que o proxy integrado está activo. Abra **80/TCP** e **443/TCP** no firewall do servidor e do fornecedor de alojamento.
3. Crie os seguintes registos DNS do tipo **A**, todos com o IPv4 público do servidor de destino. Os nomes da tabela são relativos à zona `it.ao`. Use registos AAAA apenas se o proxy também responder por IPv6 nas portas 80 e 443.

| Nome na zona `it.ao` | Tipo | Destino |
| --- | --- | --- |
| `denuncias` | A | IPv4 público do servidor Coolify |
| `api.denuncias` | A | Mesmo IPv4 |
| `admin.denuncias` | A | Mesmo IPv4 |
| `entidade.denuncias` | A | Mesmo IPv4 |

Confirme a resolução com `nslookup denuncias.it.ao` e repita para os três subdomínios. Se os registos passarem pelo proxy da Cloudflare e a emissão de certificados falhar, consulte o guia de ACME do Coolify. [Domínios e HTTPS](https://coolify.io/docs/core/networking/domains) · [Diagnóstico do Let's Encrypt](https://coolify.io/docs/troubleshoot/dns-and-domains/lets-encrypt-not-working)

## 2. Criar o recurso MySQL

1. No ambiente de produção, seleccione **+ New → Databases → MySQL**. Escolha o mesmo servidor e destino Docker que vai usar para a aplicação.
2. Em **General**, configure a imagem `mysql:8.4`, a base inicial `portal_denuncias_bd` e o utilizador normal `portal`. Guarde as palavras-passe geradas pelo Coolify. Mantenha o recurso com acesso privado e deixe os mapeamentos de portas vazios.
3. Seleccione **Start**. Quando o MySQL estiver saudável, abra **General** e consulte **MySQL URL (internal)**. Use o hostname interno que aparece entre `@` e `:3306` como valor de `DB_HOST`. Obtenha a palavra-passe do utilizador normal no campo de credenciais do recurso MySQL. [Recurso MySQL no Coolify](https://coolify.io/docs/databases/mysql)

Se já houver dados na instalação com MySQL no Compose, suspenda as escritas, faça um dump e importe-o no recurso MySQL antes de mudar a ligação da aplicação. Preserve a `APP_KEY` e o volume `evidence`, e mantenha o volume MySQL original até validar os dados importados.

## 3. Criar a aplicação

1. No Coolify, abra o projecto e o ambiente de produção e seleccione **+ New**.
2. Escolha o repositório GitHub `miguelbeatz3/Portal-Denuncias`. Para um repositório privado, use a integração GitHub App ou uma chave de deploy com acesso de leitura.
3. Escolha a branch publicada, por exemplo `main`, e o servidor cujo IP configurou no DNS.
4. Seleccione **Docker Compose** como estratégia de build. Configure **Base Directory** com `/` e **Docker Compose Location** com `/docker-compose.yml`.
5. Guarde e carregue a configuração até aparecerem `frontend` e `backend`. Use o modo Compose gerido pelo Coolify.
6. Confirme o nome da rede do destino MySQL em **Destinations**. O Compose liga o backend a essa rede durante o arranque através de `DB_NETWORK`. O destino padrão usa a rede `coolify`. [Destinos no Coolify](https://coolify.io/docs/core/networking/destinations/manage)

## 4. Configurar a ligação à base de dados

Em **Environment Variables** da aplicação, introduza os valores abaixo no ambiente de produção. Active o uso em runtime e desactive o uso durante o build.

| Variável | Valor |
| --- | --- |
| `APP_KEY` | Chave Laravel com o prefixo `base64:` |
| `DB_NETWORK` | Nome da rede Docker do destino do MySQL. O valor padrão é `coolify`. |
| `DB_HOST` | Hostname da **MySQL URL (internal)**, por exemplo o identificador do contentor que aparece no URL |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `portal_denuncias_bd` |
| `DB_USERNAME` | `portal` |
| `DB_PASSWORD` | Palavra-passe do utilizador MySQL `portal` |

Se usar um destino com outro nome de rede e `DB_NETWORK` ainda não aparecer, adicione a variável em **Environment Variables**.

Para uma instalação nova, execute este comando na pasta `backend`, depois de instalar as dependências com `composer install`. O comando mostra a chave para copiar para o Coolify sem alterar o ficheiro `.env` local.

```bash
php artisan key:generate --show
```

Guarde os valores num gestor de segredos. Em actualizações, mantenha a `APP_KEY` existente. A palavra-passe de root é gerida no recurso MySQL. [Variáveis de ambiente no Coolify](https://coolify.io/docs/applications/configuration/environment-variables)

## 5. Ligar os domínios

Em **Domains**, adicione cada endereço no serviço indicado. Os valores são URLs completas, com HTTPS e sem caminho.

| Área | Serviço | Valor a introduzir no Coolify | Endereço para o utilizador |
| --- | --- | --- | --- |
| Cidadão | `frontend` | `https://denuncias.it.ao:3000` | `https://denuncias.it.ao` |
| API | `backend` | `https://api.denuncias.it.ao` | `https://api.denuncias.it.ao/api/categories` |
| Administração | `backend` | `https://admin.denuncias.it.ao` | `https://admin.denuncias.it.ao` |
| Entidade | `backend` | `https://entidade.denuncias.it.ao` | `https://entidade.denuncias.it.ao` |

O sufixo `:3000` indica a porta interna do Next.js ao proxy. O backend usa a porta interna 80. O Coolify termina o HTTPS na porta pública 443. A Administração e a Entidade abrem em `/login` nos respectivos domínios para quem ainda não tem sessão. Os endereços anteriores `/admin/login` e `/entidade/login` redireccionam para `/login` no domínio do respectivo painel. [Domínios em aplicações Docker Compose](https://coolify.io/docs/applications/builds/docker-compose#configure-public-services)

## 6. Fazer o primeiro deploy

1. Confirme que o recurso MySQL está saudável. Na aplicação, clique em **Deploy** e acompanhe **Deployment Logs**. O backend corre `php artisan migrate --force` no arranque antes de iniciar o Apache. Confirme que `backend` e `frontend` ficam saudáveis.
2. Abra **Terminal** no serviço `backend` e execute os comandos seguintes. O último pede o nome, o e-mail e a palavra-passe sem a mostrar.

   ```bash
   php artisan migrate:status
   php artisan portal:criar-administrador
   ```

3. Para carregar o catálogo inicial de Luanda, execute os comandos abaixo. Pode repeti-los para inserir entidades e preencher campos que ainda estejam em falta. Confirme no painel os nomes, contactos e acessos dos operadores antes de encaminhar denúncias reais.

   ```bash
   php artisan portal:cadastrar-entidades-luanda
   php artisan portal:preencher-contactos-luanda
   ```

4. Entre na área da Administração e crie os operadores nas entidades correspondentes. O primeiro deploy parte de uma base vazia, preenchida pelas migrações. Caso seja necessário transportar dados de outra instalação, planeie a importação do MySQL e dos anexos privados em conjunto.

## 7. Validar

```bash
curl -fsS https://api.denuncias.it.ao/up
curl -fsS https://api.denuncias.it.ao/api/categories
curl -fsS https://denuncias.it.ao/api/municipalities
curl -fsSL -o /dev/null -w '%{http_code}\n' https://admin.denuncias.it.ao/
curl -fsSL -o /dev/null -w '%{http_code}\n' https://entidade.denuncias.it.ao/
```

O primeiro pedido verifica a saúde do Laravel. Os dois seguintes devem devolver JSON. Os painéis devem terminar no ecrã de login com HTTP 200. Para verificar o fluxo completo, registe uma denúncia fictícia com um anexo, consulte-a por referência e código, encaminhe-a no painel administrativo e confirme a sua visibilidade no operador da entidade seleccionada.

## Actualizações e cópias de segurança

- Use **Deploy** na mesma aplicação para actualizar a revisão. O recurso MySQL tem o seu próprio volume. O volume `evidence` da aplicação guarda os anexos privados. Configure as cópias agendadas no recurso MySQL e inclua o volume dos anexos nas cópias da aplicação. [Backups de bases de dados no Coolify](https://coolify.io/docs/databases/)
- Antes de actualizar e de forma regular, guarde um dump lógico de `portal_denuncias_bd` e uma cópia do volume montado em `/var/www/html/storage/app/private`. Para obter um conjunto consistente, faça ambas as cópias numa janela sem submissões ou alterações. Guarde também a `APP_KEY` no gestor de segredos e teste a reposição num ambiente separado.
- Se a API responder e o formulário falhar, compare as respostas de `api.denuncias.it.ao/api/categories` e `denuncias.it.ao/api/categories`. Consulte **Runtime Logs** de `frontend` se apenas o segundo endereço falhar. Se o Laravel falhar no arranque, consulte **Runtime Logs** de `backend` e confirme o estado do recurso MySQL, o hostname interno, as credenciais e o nome da rede em `DB_NETWORK`.
