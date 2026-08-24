# Passo a passo do deploy em produção

Este documento é o roteiro operacional para entregar a aplicação Vistoria em
produção. Ele considera inicialmente um único VPS Ubuntu/Debian com 1 CPU e
2 GB de RAM, usando:

- Nginx;
- PHP 8.3 ou superior com PHP-FPM;
- MySQL 8;
- Redis;
- Laravel Horizon;
- Supervisor;
- arquivos privados no próprio servidor.

Substitua todos os valores entre `<...>` antes de executar os comandos. Não
copie o `.env` local: o ambiente de desenvolvimento atual pode usar SQLite,
enquanto produção deve usar MySQL.

## Bloqueio funcional conhecido antes do go-live

O bootstrap cria o superadministrador global, mas o projeto ainda não possui uma
interface global para esse usuário cadastrar empresas e seus primeiros
administradores. Em um banco novo, o deploy técnico poderá ser concluído, porém o
aceite funcional ficará bloqueado até existir um fluxo aprovado de onboarding de
empresa — preferencialmente pela própria aplicação ou por um comando Artisan
auditável e idempotente. Não crie a primeira empresa com SQL manual ou Tinker em
produção. O mesmo fluxo deverá provisionar as categorias e classificações
iniciais exigidas pela empresa.

## 1. Informações que precisam estar definidas

Antes de iniciar, confirme:

```text
Domínio:                 <app.exemplo.com.br>
Caminho da aplicação:    /var/www/vistoria
Usuário de deploy:       <deploy>
Usuário do PHP/Nginx:    www-data
Banco MySQL:             vistoria
Usuário MySQL:           vistoria
E-mail do administrador: sampaio.free@gmail.com
```

Também separe previamente:

- senha forte do MySQL;
- senha forte do Redis;
- acesso ao repositório Git;
- certificado HTTPS;
- destino externo para os backups.

## 2. Preparar o servidor

Atualize o sistema e instale Nginx, MySQL, Redis, Supervisor, Composer, Node e
as extensões exigidas pelo projeto. Os nomes dos pacotes PHP podem variar
conforme o repositório da distribuição:

```bash
sudo apt update
sudo apt upgrade
sudo apt install nginx mysql-server redis-server supervisor git unzip curl composer
sudo apt install php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-redis php8.3-imagick
sudo apt install imagemagick ghostscript
```

Confirme as dependências:

```bash
php -v
php -m | grep -E 'imagick|pcntl|posix|redis|pdo_mysql'
composer --version
mysql --version
redis-server --version
nginx -v
supervisord --version
```

Instale a versão de Node indicada em `.nvmrc`, preferencialmente pelo gerenciador
adotado pela equipe de infraestrutura.

Como o VPS possui apenas 2 GB de RAM, confira `swapon --show`. Se não existir
swap, crie 2 GB antes do build e do processamento de imagens:

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Não repita esse procedimento quando `/swapfile` já estiver ativo ou registrado
em `/etc/fstab`.

## 3. Configurar o MySQL

Entre no MySQL como administrador:

```bash
sudo mysql
```

Crie banco e usuário exclusivos. Troque a senha antes de executar:

```sql
CREATE DATABASE vistoria
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'vistoria'@'127.0.0.1'
    IDENTIFIED BY '<SENHA_FORTE_MYSQL>';

GRANT ALL PRIVILEGES ON vistoria.* TO 'vistoria'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

Teste a conexão usando o novo usuário:

```bash
mysql -h 127.0.0.1 -u vistoria -p vistoria
```

## 4. Configurar o Redis

Edite `/etc/redis/redis.conf` e garanta estas opções:

```conf
bind 127.0.0.1 ::1
protected-mode yes
requirepass <SENHA_FORTE_REDIS>
appendonly yes
appendfsync everysec
maxmemory-policy noeviction
```

Reinicie e habilite o serviço:

```bash
sudo systemctl restart redis-server
sudo systemctl enable redis-server
sudo systemctl status redis-server
```

Teste com `redis-cli --askpass ping`. A resposta esperada é `PONG`.

## 5. Obter a aplicação

Crie o diretório e entregue sua propriedade ao usuário de deploy:

```bash
sudo mkdir -p /var/www/vistoria
sudo chown <deploy>:www-data /var/www/vistoria
sudo chmod 2775 /var/www/vistoria
```

Clone o repositório no diretório definido:

```bash
git clone <URL_DO_REPOSITORIO> /var/www/vistoria
cd /var/www/vistoria
git checkout <BRANCH_OU_TAG_DE_PRODUCAO>
```

Instale as dependências:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

Em um pipeline futuro, o build frontend poderá ser gerado pelo CI e entregue
como artefato, retirando a necessidade de Node no servidor.

## 6. Criar o ambiente de produção

Crie o `.env` a partir do exemplo:

```bash
cp .env.example .env
sudo chown <deploy>:www-data .env
chmod 640 .env
```

Preencha pelo menos as configurações abaixo:

```dotenv
APP_NAME="Vistoria"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://<DOMINIO>
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vistoria
DB_USERNAME=vistoria
DB_PASSWORD=<SENHA_FORTE_MYSQL>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<SENHA_FORTE_REDIS>
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE_RETRY_AFTER=240

EQUIPMENT_DOCUMENTS_ROOT=/var/lib/vistoria/equipment-documents
INSPECTION_PHOTOS_ROOT=/var/lib/vistoria/inspection-photos
INSPECTION_MAPS_ROOT=/var/lib/vistoria/inspection-maps
```

Configure também o serviço de e-mail real quando ele for utilizado. Nunca deixe
segredos no repositório, em mensagens ou em arquivos públicos.
Coloque entre aspas os valores do `.env` que contenham espaços, `#` ou outros
caracteres interpretados pelo formato dotenv.

Gere a chave uma única vez e mantenha-a nos backups de segredos:

```bash
php artisan key:generate
```

Não gere outra `APP_KEY` em deploys posteriores, pois isso invalidaria dados
criptografados, sessões e tokens existentes.

## 7. Preparar storage e permissões

Crie os diretórios privados persistentes:

```bash
sudo install -d -o www-data -g www-data -m 2770 /var/lib/vistoria
sudo install -d -o www-data -g www-data -m 2770 /var/lib/vistoria/equipment-documents
sudo install -d -o www-data -g www-data -m 2770 /var/lib/vistoria/inspection-photos
sudo install -d -o www-data -g www-data -m 2770 /var/lib/vistoria/inspection-maps
```

Ajuste os diretórios graváveis do Laravel:

```bash
sudo chown -R <deploy>:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
php artisan storage:link
```

Os diretórios de documentos, fotos e mapas são privados e não devem ser
publicados diretamente pelo Nginx.

## 8. Configurar PHP-FPM

No `php.ini` do FPM, configure:

```ini
upload_max_filesize=25M
post_max_size=30M
memory_limit=512M
max_execution_time=120
```

Confirme qual arquivo está ativo com `php --ini` para CLI e pela configuração do
pool para FPM. Reinicie o serviço depois da alteração:

```bash
sudo systemctl restart php8.3-fpm
```

O processamento de imagens usa Imagick e limita cada worker. Em um servidor de
2 GB deve existir somente um worker do Horizon.

Se mapas PDF forem usados, valide em arquivo controlado que a política de
segurança do ImageMagick permite a leitura necessária. Não desabilite globalmente
as demais proteções da política.

## 9. Configurar o Nginx

Crie `/etc/nginx/sites-available/vistoria`:

```nginx
server {
    listen 80;
    server_name <DOMINIO>;

    root /var/www/vistoria/public;
    index index.php;
    charset utf-8;

    client_max_body_size 30M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Ative e valide:

```bash
sudo ln -s /etc/nginx/sites-available/vistoria /etc/nginx/sites-enabled/vistoria
sudo nginx -t
sudo systemctl reload nginx
```

Depois configure HTTPS com a solução adotada pela infraestrutura e redirecione
HTTP para HTTPS. Só prossiga com `APP_URL=https://...` quando o certificado e o
domínio estiverem funcionais.

## 10. Executar migrations e criar o administrador

No diretório da aplicação:

```bash
php artisan migrate --force
php artisan optimize
php artisan app:bootstrap-super-admin
```

O último comando cria `sampaio.free@gmail.com`, mostra uma senha temporária forte
uma única vez e exige a troca no primeiro acesso. Entregue essa senha por canal
seguro; ela não deve aparecer em ticket, chat ou log de pipeline.

Confirme as migrations:

```bash
php artisan migrate:status
```

## 11. Configurar o Horizon no Supervisor

Use como base `deploy/supervisor/vistoria-horizon.conf.example`. Copie e revise:

```bash
sudo cp deploy/supervisor/vistoria-horizon.conf.example /etc/supervisor/conf.d/vistoria-horizon.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vistoria-horizon
```

Confira:

```bash
sudo supervisorctl status vistoria-horizon
php artisan horizon:status
```

Ambos devem indicar execução ativa. O painel `/horizon` só pode ser aberto por
um superadministrador ativo que já tenha trocado a senha temporária.

## 12. Configurar o cron

Edite o crontab do usuário responsável pela aplicação:

```bash
crontab -e
```

Adicione exatamente uma entrada:

```cron
* * * * * cd /var/www/vistoria && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Confira o scheduler:

```bash
php artisan schedule:list
```

Devem aparecer:

- `horizon:snapshot` a cada cinco minutos;
- `photos:cleanup-abandoned` às 03:00;
- `inspection-maps:cleanup-deleted` às 03:30.

O cron não processa as imagens. Os jobs da fila `images` são executados pelo
Horizon mantido pelo Supervisor.

## 13. Teste de aceite do primeiro deploy

Execute os diagnósticos:

```bash
php artisan about
php artisan migrate:status
php artisan horizon:status
php artisan schedule:list
php artisan config:show queue
curl -fsS https://<DOMINIO>/up
```

Valide manualmente:

1. entrar com `sampaio.free@gmail.com` e trocar a senha;
2. confirmar que `/horizon` abre apenas para o superadministrador;
3. cadastrar a primeira empresa e seu administrador pelo fluxo de onboarding
   aprovado; se ele ainda não existir, registrar o bloqueio e não liberar o
   sistema para operação;
4. criar uma inspeção e uma avaliação de avaria;
5. enviar fotografia JPEG, PNG ou WebP pelo celular;
6. confirmar no Horizon a passagem por `pending`, `processing` e `ready`;
7. abrir a miniatura e a versão otimizada;
8. testar uma fotografia próxima de 25 MB;
9. verificar logs do Laravel, Nginx, PHP-FPM, Redis e Supervisor;
10. reiniciar o VPS e confirmar que todos os serviços voltaram automaticamente.

O deploy só deve ser considerado aprovado depois desse teste.

## 14. Deploys seguintes

Antes de cada deploy, tenha backup recente e confirme que o repositório está na
branch correta. No servidor:

```bash
cd /var/www/vistoria
php artisan down --retry=60
git fetch --all --tags --prune
git checkout <BRANCH_DE_PRODUCAO>
git pull --ff-only origin <BRANCH_DE_PRODUCAO>
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan horizon:terminate
php artisan up
```

O Supervisor deve reiniciar o Horizon com o código novo. Valide imediatamente:

```bash
sudo supervisorctl status vistoria-horizon
php artisan horizon:status
curl -fsS https://<DOMINIO>/up
```

Quando a equipe publicar releases por tags imutáveis, substitua os três comandos
de checkout/pull por `git checkout --detach <TAG_DE_PRODUCAO>` após o `git fetch`.

Antes da primeira troca de `QUEUE_CONNECTION=database` para Redis, não deixe jobs
na tabela `jobs`. Drene-os ainda no release antigo:

```bash
php artisan queue:work database --queue=images,default --stop-when-empty --timeout=210
```

Nunca apague jobs pendentes para concluir um deploy.

## 15. Backup e rollback

O backup deve incluir:

- banco MySQL;
- `.env` ou cofre de segredos equivalente;
- `/var/lib/vistoria/equipment-documents`;
- `/var/lib/vistoria/inspection-photos`;
- `/var/lib/vistoria/inspection-maps`.

Armazene cópias fora do VPS e teste periodicamente a restauração.

Para rollback de código, retorne ao último tag/release aprovado, execute
`composer install`, restaure os assets correspondentes, rode `php artisan optimize`
e `php artisan horizon:terminate`. Não execute `migrate:rollback` automaticamente:
migrations de produção devem ser avaliadas individualmente. Se uma migration
destrutiva já tiver sido aplicada, use o procedimento de restauração do backup.

## 16. Evidências para encerrar a entrega

O responsável pelo deploy deve registrar, sem incluir segredos:

- tag ou commit implantado;
- horário do deploy;
- resultado de `migrate:status`;
- status do Supervisor e Horizon;
- resultado de `/up`;
- confirmação do cron;
- resultado do upload e processamento de uma fotografia;
- localização e data do último backup;
- incidentes ou ajustes realizados.
