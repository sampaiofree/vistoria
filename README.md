# Vistoria

Aplicação web multiempresa para planejar, executar, revisar e liberar inspeções
técnicas. O projeto usa Laravel 13, Inertia 3, Vue 3, MySQL 8, Redis e Horizon.
A [documentação](docs/README.md) reúne o comportamento do produto, as matrizes
técnicas, a arquitetura e a operação.

## Requisitos locais

- PHP 8.3 ou superior, Composer 2 e extensões `pdo_mysql`, `redis`, `imagick`,
  `pcntl` e `posix`;
- Node conforme `.nvmrc`;
- MySQL 8 e Redis.

## Instalação local

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
php artisan migrate
npm run build
php artisan app:bootstrap-super-admin --email=admin@exemplo.com
```

O `.env.example` usa MySQL, fila Redis e armazenamento local privado. Ajuste as
credenciais do banco e confirme que `redis-cli ping` responde `PONG`. O
`DatabaseSeeder` não cria contas ou dados operacionais. Para iniciar
servidor, Horizon, logs e Vite:

```bash
composer run dev
```

O painel `/horizon` exige superadministrador ativo com senha definitiva. O
comando de bootstrap mostra uma senha temporária somente na criação e exige
troca no primeiro acesso. Use um e-mail controlado por você.

## Recursos e operação

- [Fluxo de inspeções](docs/produto/inspecoes.md) e
  [avarias/reinspeções](docs/produto/avarias.md);
- [Classificações e quantitativos](docs/referencia/classificacoes.md),
  [resumo e Nota M2](docs/produto/classificacao-e-m2.md) e
  [relatórios](docs/produto/relatorios.md);
- [Deploy](docs/operacao/deploy.md) e
  [armazenamento/backup](docs/operacao/armazenamento-e-backup.md).

O aplicativo pode ser instalado no Android pelo Chrome em HTTPS. A identidade
visual e o ícone PWA são configurados por empresa; a operação requer internet e
não oferece modo offline. Fotos, mapas e identidade visual usam discos privados,
locais ou R2 conforme `USER_IMAGES_STORAGE`.

## Verificação

```bash
composer validate --strict --no-check-publish
vendor/bin/pint --test
php artisan test
npm run test:js
npm run build
```

A suíte padrão usa SQLite em memória. O fluxo de CI também valida migrations e
testes no MySQL.
