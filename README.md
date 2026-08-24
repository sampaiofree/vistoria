# Vistoria

Aplicação web multiempresa para inspeções técnicas, inicialmente focada na categoria CIVIL.

O projeto usa Laravel 13, Inertia, Vue 3, MySQL 8 e Vite. O escopo, as decisões de arquitetura e a sequência de implementação estão em [`docs/00-INDICE-E-ROADMAP.md`](docs/00-INDICE-E-ROADMAP.md).

## Requisitos

- PHP 8.3 ou superior;
- Composer 2;
- Node conforme `.nvmrc`;
- MySQL 8;
- Redis com a extensão PHP `redis`;
- Imagick; `pcntl` e `posix` para o Horizon.

## Instalação local

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
php artisan migrate --seed
npm run build
```

Confirme que o Redis local está disponível com `redis-cli ping`. O comando deve
responder `PONG`. O `.env.example` já configura a fila Redis.

### Administrador mestre de produção

Após executar as migrations no ambiente de produção, crie o administrador mestre
manualmente em um terminal seguro:

```bash
php artisan app:bootstrap-super-admin
```

O comando cria `sampaio.free@gmail.com` como superadministrador global, gera uma
senha temporária forte e a exibe somente nessa execução. A conta será obrigada a
trocar a senha no primeiro acesso. Se o comando for executado novamente, uma conta
já configurada não terá a senha nem os dados alterados. Caso o e-mail já pertença
a outro tipo de usuário, o comando falhará sem promover essa conta.

Para desenvolvimento:

```bash
composer run dev
```

Esse comando inicia servidor HTTP, Horizon, logs e Vite. O painel de filas fica
em `/horizon` e exige login como superadministrador ativo com senha definitiva.

### Limite para fotografias

O upload de fotografias aceita arquivos de até 25 MB. O arquivo `public/.user.ini`
configura esse limite em ambientes PHP-FPM que respeitam configurações por diretório.
No Laravel Herd, ajuste também `upload_max_filesize` e `post_max_size` na configuração
da versão ativa do PHP. O servidor HTTP ou proxy reverso deve aceitar corpos de pelo
menos 30 MB (por exemplo, `client_max_body_size 30M` no Nginx). Depois de alterar a
configuração, reinicie o PHP-FPM e o servidor HTTP antes de validar o envio.

As credenciais previsíveis do `DevelopmentSeeder` são criadas somente nos ambientes `local` e `testing`.

## Produção

O provisionamento de Redis, Horizon, Supervisor, cron, storage privado, backup e
testes de aceite está documentado em
[`docs/13-DEPLOY-HETZNER.md`](docs/13-DEPLOY-HETZNER.md). O roteiro operacional
completo para repasse está em
[`docs/13A-PASSO-A-PASSO-DEPLOY-PRODUCAO.md`](docs/13A-PASSO-A-PASSO-DEPLOY-PRODUCAO.md).

## Demonstração View First

O cenário visual do documento 06B pode ser restaurado sem recriar o banco:

```bash
php artisan db:seed --class=ViewFirstDemoSeeder
```

Crie uma organização e um usuário pelos fluxos administrativos da aplicação. O sistema não fornece mais credenciais ou dados fictícios.

## Qualidade

```bash
composer validate --strict --no-check-publish
vendor/bin/pint --test
php artisan test
npm run build
```

A suíte local usa SQLite em memória para feedback rápido. As migrations, o rollback, o seed idempotente e a suíte completa também foram validados no MySQL 8.4.11, que é o banco alvo. O workflow de CI repete essa validação a cada envio.

## Estado atual

- fundação multiempresa: implementada e validada no banco alvo, aguardando conferência visual completa;
- clientes e estrutura operacional: implementados e validados no banco alvo, aguardando conferência visual e responsiva completa;
- equipamentos e documentos: implementados, validados e integrados ao shell operacional;
- inspeções e fluxo: implementados com responsáveis, estados, histórico, referências e telas operacionais;
- dashboard e navegação: implementados e validados entre 375 e 1440 px;
- View First 06B: fluxo visual local implementado e validado para apresentação, com dados provisórios explicitamente identificados;
- avarias e reinspeções: implementação parcial, com identidade permanente e avaliações reais já reutilizadas pelo 06B;
- upload de fotos, cálculo oficial de GUT/CV, revisão, PDF e deploy: permanecem planejados no roadmap.
