# Publicar o Portal de Denúncias no Coolify

O repositório contém uma aplicação Docker Compose com três serviços. `frontend` serve o portal do cidadão, `backend` serve a API e os dois painéis Laravel, e `db` executa o MySQL 8.4. O frontend encaminha os pedidos `/api/...` para `http://backend` pela rede do Compose. O MySQL fica nessa mesma rede sem porta publicada.

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

## 2. Criar a aplicação

1. No Coolify, abra o projecto e o ambiente de produção e seleccione **+ New**.
2. Escolha o repositório GitHub `miguelbeatz3/Portal-Denuncias`. Para um repositório privado, use a integração GitHub App ou uma chave de deploy com acesso de leitura.
3. Escolha a branch publicada, por exemplo `main`, e o servidor cujo IP configurou no DNS.
4. Seleccione **Docker Compose** como estratégia de build. Configure **Base Directory** com `/` e **Docker Compose Location** com `/docker-compose.yml`.
5. Guarde e carregue a configuração até aparecerem os três serviços. Use o modo Compose gerido pelo Coolify para que o proxy seja configurado pelos domínios da aplicação. [Docker Compose no Coolify](https://coolify.io/docs/applications/builds/docker-compose)

## 3. Configurar os segredos

Em **Environment Variables**, preencha os três valores no ambiente de produção. Active o uso em runtime e desactive o uso durante o build para cada segredo. Use os valores sem aspas nem espaços adicionais.

| Variável | Valor |
| --- | --- |
| `APP_KEY` | Chave Laravel com o prefixo `base64:` |
| `DB_PASSWORD` | Palavra-passe do utilizador MySQL `portal` |
| `MYSQL_ROOT_PASSWORD` | Palavra-passe distinta do administrador MySQL |

Para uma instalação nova, execute estes comandos num terminal com PHP. O primeiro gera a chave Laravel. Execute a segunda linha duas vezes para obter as duas palavras-passe da base de dados.

```bash
php -r 'echo "base64:", base64_encode(random_bytes(32)), PHP_EOL;'
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Guarde os valores num gestor de segredos. Em actualizações, mantenha a `APP_KEY` existente. As palavras-passe iniciais do MySQL são aplicadas ao criar o volume. Para rodar uma palavra-passe com o volume já em uso, altere também a conta no MySQL. [Variáveis de ambiente no Coolify](https://coolify.io/docs/applications/configuration/environment-variables)

## 4. Ligar os domínios

Em **Domains**, adicione cada endereço no serviço indicado. Os valores são URLs completas, com HTTPS e sem caminho.

| Área | Serviço | Valor a introduzir no Coolify | Endereço para o utilizador |
| --- | --- | --- | --- |
| Cidadão | `frontend` | `https://denuncias.it.ao:3000` | `https://denuncias.it.ao` |
| API | `backend` | `https://api.denuncias.it.ao` | `https://api.denuncias.it.ao/api/categories` |
| Administração | `backend` | `https://admin.denuncias.it.ao` | `https://admin.denuncias.it.ao` |
| Entidade | `backend` | `https://entidade.denuncias.it.ao` | `https://entidade.denuncias.it.ao` |

O sufixo `:3000` indica a porta interna do Next.js ao proxy. O backend usa a porta interna 80. O Coolify termina o HTTPS na porta pública 443. O Laravel redirecciona as raízes de Administração e Entidade para os respectivos painéis em `/admin` e `/entidade`. [Domínios em aplicações Docker Compose](https://coolify.io/docs/applications/builds/docker-compose#configure-public-services)

## 5. Fazer o primeiro deploy

1. Clique em **Deploy** e acompanhe **Deployment Logs**. O backend aguarda o MySQL e corre `php artisan migrate --force` no arranque antes de iniciar o Apache. Confirme que `db`, `backend` e `frontend` ficam saudáveis.
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

## 6. Validar

```bash
curl -fsS https://api.denuncias.it.ao/up
curl -fsS https://api.denuncias.it.ao/api/categories
curl -fsS https://denuncias.it.ao/api/municipalities
curl -fsSL -o /dev/null -w '%{http_code}\n' https://admin.denuncias.it.ao/
curl -fsSL -o /dev/null -w '%{http_code}\n' https://entidade.denuncias.it.ao/
```

O primeiro pedido verifica a saúde do Laravel. Os dois seguintes devem devolver JSON. Os painéis devem terminar no ecrã de login com HTTP 200. Para verificar o fluxo completo, registe uma denúncia fictícia com um anexo, consulte-a por referência e código, encaminhe-a no painel administrativo e confirme a sua visibilidade no operador da entidade seleccionada.

## Actualizações e cópias de segurança

- Use **Deploy** na mesma aplicação para actualizar a revisão. Os volumes persistentes `mysql` e `evidence` guardam, respectivamente, os dados do MySQL e os anexos privados. Preserve os dois volumes ao substituir imagens ou recriar containers.
- Antes de actualizar e de forma regular, guarde um dump lógico de `portal_denuncias_bd` e uma cópia do volume montado em `/var/www/html/storage/app/private`. Para obter um conjunto consistente, faça ambas as cópias numa janela sem submissões ou alterações. Guarde também a `APP_KEY` no gestor de segredos e teste a reposição num ambiente separado.
- Se a API responder e o formulário falhar, compare as respostas de `api.denuncias.it.ao/api/categories` e `denuncias.it.ao/api/categories`. Consulte **Runtime Logs** de `frontend` se apenas o segundo endereço falhar. Para erros de arranque do Laravel, consulte primeiro as migrações em **Runtime Logs** de `backend` e o estado de `db`.
