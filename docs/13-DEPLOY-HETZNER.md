# Deploy em produção — Horizon, Redis e checklist

O roteiro completo, pronto para repasse ao responsável pela infraestrutura,
está em [`13A-PASSO-A-PASSO-DEPLOY-PRODUCAO.md`](13A-PASSO-A-PASSO-DEPLOY-PRODUCAO.md).

Este checklist considera uma instalação inicial na Hetzner ou VPS equivalente,
com 1 CPU e
2 GB de RAM. Aplicação, MySQL, Redis e Horizon executam no mesmo servidor, e os
arquivos privados permanecem em disco local.

## 1. Dependências do servidor

- PHP 8.3 ou superior com `imagick`, `pcntl`, `posix`, `redis` e `pdo_mysql`;
- MySQL 8;
- Redis;
- ImageMagick e Ghostscript para mapas originados de PDF;
- Nginx, Supervisor, Composer 2 e HTTPS válido;
- Node somente no build, caso os assets não sejam gerados no CI.

O Redis deve aceitar conexões apenas locais. Use autenticação, persistência AOF
e impeça a expulsão silenciosa de jobs:

```conf
bind 127.0.0.1 ::1
protected-mode yes
requirepass TROQUE_POR_UM_SEGREDO_FORTE
appendonly yes
appendfsync everysec
maxmemory-policy noeviction
```

## 2. Ambiente

Configuração mínima relevante no `.env` de produção:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vistoria
DB_USERNAME=vistoria
DB_PASSWORD=SEGREDO_DO_MYSQL

QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=SEGREDO_DO_REDIS
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE_RETRY_AFTER=240
```

Defina caminhos privados persistentes, fora do diretório substituído a cada
deploy, e conceda acesso ao usuário do PHP e do Horizon:

```dotenv
EQUIPMENT_DOCUMENTS_ROOT=/var/lib/vistoria/equipment-documents
INSPECTION_PHOTOS_ROOT=/var/lib/vistoria/inspection-photos
INSPECTION_MAPS_ROOT=/var/lib/vistoria/inspection-maps
```

Configure PHP-FPM com `upload_max_filesize=25M` e `post_max_size=30M`, e Nginx
com `client_max_body_size 30M`.

## 3. Primeiro deploy

Antes de trocar uma instalação existente de fila `database` para Redis, confira
a tabela `jobs`. Se houver registros, mantenha o release anterior e drene a fila:

```bash
php artisan queue:work database --queue=images,default --stop-when-empty --timeout=210
```

Não apague jobs pendentes. Depois que a tabela estiver vazia, faça o deploy:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan app:bootstrap-super-admin
```

O último comando é idempotente, mas a senha temporária só é exibida quando a
conta mestre é criada.

## 4. Horizon e Supervisor

Copie `deploy/supervisor/vistoria-horizon.conf.example` para
`/etc/supervisor/conf.d/vistoria-horizon.conf` e ajuste caminho, usuário e log.
Depois ative o processo:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vistoria-horizon
php artisan horizon:status
```

Em cada deploy posterior, recarregue os caches e encerre o processo mestre de
forma graciosa. O Supervisor iniciará a nova versão:

```bash
php artisan optimize
php artisan horizon:terminate
```

O painel fica em `/horizon` e exige um superadministrador global ativo que já
tenha trocado a senha temporária.

## 5. Scheduler

Adicione uma única entrada no crontab do usuário da aplicação:

```cron
* * * * * cd /var/www/vistoria && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Confira com `php artisan schedule:list`. O scheduler registra métricas do Horizon
a cada cinco minutos, limpa uploads abandonados às 03:00 e remove mapas elegíveis
às 03:30. O cron não executa os jobs de imagem; essa responsabilidade é do Horizon.

## 6. Aceite e operação

```bash
php artisan about
php artisan migrate:status
php artisan horizon:status
php artisan schedule:list
php artisan config:show queue
```

- enviar uma fotografia real e confirmar `pending`, `processing` e `ready`;
- conferir original, WebP otimizado e miniatura;
- abrir `/horizon` como superadmin e confirmar métricas e jobs recentes;
- verificar `/up`, logs do Laravel, Redis e Supervisor;
- reiniciar o VPS e confirmar Redis, PHP-FPM, Nginx e Horizon ativos;
- automatizar backup do MySQL e de `/var/lib/vistoria` e testar restauração;
- monitorar memória, disco, jobs com falha e tempo de espera da fila.
