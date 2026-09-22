# Deploy em produção — referência rápida

> **Tipo:** runbook derivado do repositório. Este documento não comprova que uma
> VPS Hetzner específica esteja provisionada, homologada ou em execução.

Este documento resume os requisitos de produção observados no código atual. O
procedimento completo está em
[`14A-PASSO-A-PASSO-DEPLOY-PRODUCAO.md`](14A-PASSO-A-PASSO-DEPLOY-PRODUCAO.md).

O cenário de referência é uma VPS Hetzner ou equivalente com Nginx, PHP-FPM,
MySQL, Redis, Horizon e Supervisor. Os mesmos contratos valem para outra
infraestrutura.

## Dependências

- PHP 8.3 ou superior, com `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`,
  `bcmath`, `intl`, `redis`, `imagick`, `pcntl` e `posix`;
- MySQL 8, Redis, Nginx, Supervisor e Composer 2;
- Node 22, conforme `.nvmrc`, apenas onde o frontend for compilado;
- ImageMagick para o processamento de imagens.

O fluxo atual aceita apenas imagens PNG, JPEG e WebP como fonte de mapas.
Ghostscript só é necessário para processar fontes PDF históricas já presentes
em uma instalação antiga; não faz parte do upload atual.

## Ambiente essencial

```dotenv
APP_NAME="Vistoria"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.exemplo.com.br
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vistoria
DB_USERNAME=vistoria
DB_PASSWORD=SEGREDO_MYSQL

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=SEGREDO_REDIS
REDIS_PORT=6379
REDIS_QUEUE_RETRY_AFTER=240

INSPECTION_PHOTOS_ROOT=/var/lib/vistoria/inspection-photos
INSPECTION_MAPS_ROOT=/var/lib/vistoria/inspection-maps
```

Gere `APP_KEY` apenas na primeira instalação. O valor deve ser preservado como
segredo entre releases e restaurações.

## Arquivos persistentes

Os três caminhos privados acima devem existir fora de diretórios descartáveis
de release e ser graváveis por PHP-FPM e Horizon. Eles nunca devem ser servidos
diretamente pelo Nginx.

`storage/app/public` também contém identidade visual persistente de empresas e
clientes. Em deploys por releases, mantenha `storage/` em área compartilhada e
ligue cada release a ela. Em deploy no mesmo diretório, inclua essa pasta no
backup. Execute `php artisan storage:link` para publicar somente o disco
`public`.

## Uploads e filas

- Nginx: `client_max_body_size 60M`;
- PHP-FPM: `upload_max_filesize=50M` e `post_max_size=60M`;
- conexão de fila: Redis;
- filas consumidas pelo Horizon: `images` e `default`;
- um processo, 512 MB, timeout de 210 segundos e três tentativas;
- jobs de imagem: timeout de 180 segundos e backoff de 10, 60 e 300 segundos;
- `REDIS_QUEUE_RETRY_AFTER=240` mantém o retry posterior ao timeout do worker.

Os 50 MB acomodam a origem de mapa, que é o maior upload atual. Fotografias e
documentos continuam validados pela aplicação em 25 MB.

O arquivo de referência do Supervisor é
`deploy/supervisor/vistoria-horizon.conf.example`. Ajuste diretório, usuário e
log antes de instalá-lo.

## Primeiro release

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan app:bootstrap-super-admin --email=admin@exemplo.com --name="Administrador Master"
```

O bootstrap é idempotente para uma conta global compatível. Na criação, exibe
uma senha temporária uma única vez e obriga sua troca no primeiro acesso. Não
grave essa senha em logs de automação.

Depois da troca de senha, o superadministrador usa `/admin/organizations` para
criar a primeira empresa e seu administrador. O catálogo técnico nativo é
compartilhado pelo código e não é provisionado como configuração da organização;
não há seed operacional de demonstração.

## Horizon, scheduler e saúde

Mantenha `php artisan horizon` sob o Supervisor. Em cada release posterior,
execute `php artisan horizon:terminate` depois de instalar o novo código e
recriar os caches; o Supervisor inicia o processo novamente.

O crontab possui uma única entrada:

```cron
* * * * * cd /var/www/vistoria && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

O único evento agendado pela aplicação é `horizon:snapshot`, a cada cinco
minutos e sem sobreposição. Não existem comandos agendados de limpeza ou retry
manual. O cron coleta métricas; Horizon é quem processa as filas.

Verificações mínimas:

```bash
php artisan about
php artisan migrate:status
php artisan horizon:status
php artisan schedule:list
php artisan config:show queue
curl -fsS https://app.exemplo.com.br/up
```

Faça backup consistente do MySQL, dos três diretórios privados, de
`storage/app/public` e dos segredos. Teste a restauração e monitore espaço em
disco, memória, filas com falha e latência das filas.
