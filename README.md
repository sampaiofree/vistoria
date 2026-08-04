# Vistoria

Aplicação web multiempresa para inspeções técnicas, inicialmente focada na categoria CIVIL.

O projeto usa Laravel 13, Inertia, Vue 3, MySQL 8 e Vite. O escopo, as decisões de arquitetura e a sequência de implementação estão em [`docs/00-INDICE-E-ROADMAP.md`](docs/00-INDICE-E-ROADMAP.md).

## Requisitos

- PHP 8.3 ou superior;
- Composer 2;
- Node conforme `.nvmrc`;
- MySQL 8.

## Instalação local

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
php artisan migrate --seed
npm run build
```

Para desenvolvimento:

```bash
composer run dev
```

As credenciais previsíveis do `DevelopmentSeeder` são criadas somente nos ambientes `local` e `testing`.

## Demonstração View First

O cenário visual do documento 06B pode ser restaurado sem recriar o banco:

```bash
php artisan db:seed --class=ViewFirstDemoSeeder
```

Use `demo@vistoria.test` com a senha `password`. O fluxo parte da dashboard e percorre o equipamento `U03-06VT002`, a reinspeção atual, as avarias, a avaliação CIVIL, as evidências demonstrativas e a prévia do relatório. Consulte o roteiro completo em [`docs/06B-VIEW-FIRST-DEMO.md`](docs/06B-VIEW-FIRST-DEMO.md).

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
