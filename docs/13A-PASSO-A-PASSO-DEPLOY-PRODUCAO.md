# Passo a passo do deploy em produção

Este roteiro descreve uma instalação em VPS Ubuntu/Debian com Nginx, PHP-FPM,
MySQL, Redis, Laravel Horizon e Supervisor. Ajuste nomes de pacotes, serviços e
caminhos à distribuição utilizada. Substitua os valores `<...>` antes de
executar os exemplos.

O checklist resumido está em [`13-DEPLOY-HETZNER.md`](13-DEPLOY-HETZNER.md).

## 1. Dados e segredos necessários

Defina antes do início:

```text
Domínio:                 <app.exemplo.com.br>
Caminho da aplicação:    /var/www/vistoria
Usuário de deploy:       <deploy>
Usuário PHP/Horizon:     www-data
Banco e usuário MySQL:   vistoria
E-mail do superadmin:    <admin@exemplo.com>
```

Separe senhas fortes para MySQL e Redis, credencial de leitura do repositório,
certificado HTTPS e um destino externo de backup. O ambiente de produção deve
ser próprio; não copie `.env` de desenvolvimento.

## 2. Instalar dependências

A aplicação requer PHP 8.3 ou superior. Um conjunto típico de pacotes é:

```bash
sudo apt update
sudo apt install nginx mysql-server redis-server supervisor git unzip curl composer
sudo apt install php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-redis php8.3-imagick
```

`pcntl` e `posix` também precisam estar disponíveis para Horizon. Instale a
versão de Node indicada em `.nvmrc` — atualmente 22 — no servidor de build. Se
o CI entregar `public/build`, Node não precisa ficar na VPS.

ImageMagick é necessário. Ghostscript é opcional e serve somente para fontes
PDF históricas: a interface atual de mapas aceita PNG, JPEG e WebP.

Confirme o ambiente:

```bash
php -v
php -m | grep -E 'imagick|pcntl|posix|redis|pdo_mysql'
composer --version
mysql --version
redis-server --version
nginx -v
supervisord --version
```

Em uma VPS pequena, disponibilize swap e acompanhe seu uso. O Horizon atual usa
um único worker de até 512 MB, além de PHP-FPM, banco e Redis.

## 3. Preparar MySQL e Redis

Crie banco e usuário exclusivos:

```sql
CREATE DATABASE vistoria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vistoria'@'127.0.0.1' IDENTIFIED BY '<SENHA_MYSQL>';
GRANT ALL PRIVILEGES ON vistoria.* TO 'vistoria'@'127.0.0.1';
FLUSH PRIVILEGES;
```

No Redis, restrinja a escuta à máquina, use autenticação e persistência e não
expulse silenciosamente jobs:

```conf
bind 127.0.0.1 ::1
protected-mode yes
requirepass <SENHA_REDIS>
appendonly yes
appendfsync everysec
maxmemory-policy noeviction
```

Depois de reiniciar o serviço, `redis-cli --askpass ping` deve retornar `PONG`.

## 4. Obter e compilar a aplicação

```bash
sudo mkdir -p /var/www/vistoria
sudo chown <deploy>:www-data /var/www/vistoria
sudo chmod 2775 /var/www/vistoria
git clone <URL_REPOSITORIO> /var/www/vistoria
cd /var/www/vistoria
git checkout <TAG_OU_BRANCH_APROVADA>
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

Prefira releases imutáveis ou tags aprovadas. Não use `composer update` no
servidor: produção deve respeitar o lockfile.

## 5. Configurar o ambiente

Crie `.env`, restrinja sua leitura e preencha ao menos:

```dotenv
APP_NAME="Vistoria"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://<DOMINIO>
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vistoria
DB_USERNAME=vistoria
DB_PASSWORD=<SENHA_MYSQL>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=240

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<SENHA_REDIS>
REDIS_PORT=6379

EQUIPMENT_DOCUMENTS_ROOT=/var/lib/vistoria/equipment-documents
INSPECTION_PHOTOS_ROOT=/var/lib/vistoria/inspection-photos
INSPECTION_MAPS_ROOT=/var/lib/vistoria/inspection-maps
```

Adicione a configuração real de e-mail para que notificações possam ser
entregues. Valores dotenv com espaços ou caracteres especiais devem ficar entre
aspas. Nunca envie o arquivo ou seus segredos ao repositório.

Na primeira instalação, gere a chave uma vez:

```bash
php artisan key:generate
```

Preserve `APP_KEY` nos deploys e backups. Alterá-la invalida dados criptografados,
sessões e tokens existentes.

## 6. Preparar armazenamento persistente

Crie os discos privados fora de um diretório de release descartável:

```bash
sudo install -d -o www-data -g www-data -m 2770 /var/lib/vistoria
sudo install -d -o www-data -g www-data -m 2770 /var/lib/vistoria/equipment-documents
sudo install -d -o www-data -g www-data -m 2770 /var/lib/vistoria/inspection-photos
sudo install -d -o www-data -g www-data -m 2770 /var/lib/vistoria/inspection-maps
```

Documentos, fotos e mapas são privados. O Nginx não deve expor esses caminhos;
as respostas autorizadas passam pela aplicação.

Também preserve `storage/app/public`, onde ficam logos e ícones. Em um deploy
por releases, compartilhe todo o diretório `storage/` entre releases. Em um
deploy no mesmo checkout, não o apague e inclua-o no backup.

```bash
sudo chown -R <deploy>:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
php artisan storage:link
```

O link publica somente `storage/app/public` em `public/storage`.

## 7. Ajustar PHP-FPM e Nginx

No `php.ini` do FPM:

```ini
upload_max_filesize=50M
post_max_size=60M
memory_limit=512M
max_execution_time=120
```

Reinicie o FPM. A aplicação aceita fontes de mapa de até 50 MB; fotografias e
documentos têm limite de 25 MB. O limite HTTP deve comportar o maior arquivo e os
campos multipart. No bloco Nginx:

```nginx
server {
    listen 80;
    server_name <DOMINIO>;
    root /var/www/vistoria/public;
    index index.php;
    charset utf-8;
    client_max_body_size 60M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Valide com `nginx -t`, recarregue o serviço e configure HTTPS antes de liberar
o acesso. Mantenha `APP_URL` coerente com a URL pública.

## 8. Banco, caches e superadministrador

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan app:bootstrap-super-admin --email=<ADMIN_EMAIL> --name="Administrador Master"
```

O comando de bootstrap:

- cria uma conta global ativa, sem organização;
- gera uma senha temporária de 24 caracteres e a exibe somente na criação;
- exige troca de senha no primeiro acesso;
- não altera uma conta global compatível já existente;
- falha sem alterar dados se o e-mail pertencer a uma conta incompatível.

Entregue a senha por canal seguro; não a registre em pipeline, ticket ou chat.
Após o primeiro login e a troca, o superadministrador deve criar empresas em
`/admin/organizations`. Cada criação gera o administrador inicial da empresa e
provisiona automaticamente CV, TAC e REC. Não execute seeders de demonstração.

## 9. Instalar Horizon no Supervisor

O Horizon consome `images` e `default`. A configuração atual mantém um único
processo, três tentativas, 512 MB e timeout de 210 segundos. Os jobs de imagem
têm timeout de 180 segundos e backoff de 10, 60 e 300 segundos; por isso o
ambiente usa `REDIS_QUEUE_RETRY_AFTER=240`.

Use o arquivo versionado como base:

```bash
sudo cp deploy/supervisor/vistoria-horizon.conf.example /etc/supervisor/conf.d/vistoria-horizon.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vistoria-horizon
sudo supervisorctl status vistoria-horizon
php artisan horizon:status
```

Revise primeiro `command`, `directory`, `user` e `stdout_logfile`. O usuário do
worker precisa das mesmas permissões de escrita nos storages. O exemplo usa
`stopwaitsecs=300`, maior que o timeout do worker, para encerramento gracioso.

O painel `/horizon` exige autenticação, usuário ativo, senha já trocada e acesso
de superadministrador global.

## 10. Instalar o scheduler

No crontab do usuário da aplicação, adicione somente:

```cron
* * * * * cd /var/www/vistoria && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Confira com:

```bash
php artisan schedule:list
```

O código agenda apenas `horizon:snapshot` a cada cinco minutos, com prevenção
de sobreposição. Não há comandos próprios de limpeza ou de retry manual. O cron
registra métricas; os jobs são consumidos pelo Horizon.

## 11. Aceite do primeiro deploy

Execute:

```bash
php artisan about
php artisan migrate:status
php artisan horizon:status
php artisan schedule:list
php artisan config:show queue
curl -fsS https://<DOMINIO>/up
```

Valide no navegador:

1. entrar como superadministrador e trocar a senha temporária;
2. confirmar que `/horizon` não é acessível a contas comuns;
3. criar uma empresa e guardar com segurança a credencial temporária de seu
   administrador;
4. entrar como administrador da empresa e confirmar a taxonomia CV/TAC/REC;
5. criar a estrutura operacional, um equipamento e uma inspeção;
6. enviar foto JPEG, PNG ou WebP, inclusive uma próxima de 25 MB;
7. confirmar no Horizon a execução na fila `images` e a disponibilidade das
   variantes otimizada e miniatura;
8. enviar uma imagem de mapa e validar fundo, editor e miniatura;
9. conferir `/up` e logs de Laravel, Nginx, PHP-FPM, Redis e Supervisor;
10. reiniciar a VPS e confirmar que todos os serviços retornam.

## 12. Releases posteriores

O mecanismo de atualização do código depende da estratégia da equipe. Em um
checkout simples, a sequência de aplicação é:

```bash
cd /var/www/vistoria
php artisan down --retry=60
git fetch --all --tags --prune
git checkout <TAG_OU_BRANCH_APROVADA>
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan horizon:terminate
php artisan up
```

O Supervisor reinicia Horizon com o código novo. Confirme imediatamente:

```bash
sudo supervisorctl status vistoria-horizon
php artisan horizon:status
curl -fsS https://<DOMINIO>/up
```

Evite concorrência de dois deploys. Preserve o storage compartilhado, `.env` e
`APP_KEY` em toda troca de release.

## 13. Backup e rollback

O backup precisa incluir:

- dump consistente do MySQL;
- `.env` ou os segredos equivalentes;
- os três diretórios privados em `/var/lib/vistoria`;
- `storage/app/public`, com as identidades visuais.

Armazene uma cópia fora da VPS e teste periodicamente a restauração conjunta de
banco e arquivos.

Para rollback de código, volte ao último release aprovado, reinstale exatamente
as dependências e assets daquele release, execute `php artisan optimize` e
`php artisan horizon:terminate`. Não automatize `migrate:rollback`: cada
migration deve ser avaliada, e perda de dados deve ser tratada por restauração.

## 14. Evidências operacionais

Registre sem segredos:

- commit ou tag implantado e horário;
- resultado das migrations;
- status de Supervisor e Horizon;
- resposta de `/up`;
- saída do scheduler;
- resultado de um upload e processamento reais;
- localização e data do último backup.
