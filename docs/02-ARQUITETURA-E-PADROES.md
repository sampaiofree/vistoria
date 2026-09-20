# 02 — Arquitetura e padrões atuais

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.3+, Laravel 13, Eloquent e validação por Form Requests |
| Frontend | Inertia 3, Vue 3, Tailwind CSS 4 e Vite 8 |
| Editor de texto | Tiptap |
| Exportação | html2canvas, jsPDF e docx, executados no navegador |
| Banco alvo | MySQL 8 |
| Fila | Redis e Laravel Horizon |
| Imagens | Imagick/ImageMagick; Ghostscript para leitura de PDF |
| Testes | PHPUnit e Node Test Runner |

O sistema é um monólito Laravel renderizado por Inertia. Não existe API pública no
estado atual.

## Fluxo de uma requisição

```text
rota web
→ middleware de autenticação, status, senha e tenant
→ Form Request e Policy
→ Controller
→ Action transacional ou Service de domínio
→ Eloquent / filesystem / fila
→ redirect ou página Inertia
```

- Controllers resolvem recursos dentro do tenant, autorizam e montam respostas.
- Form Requests normalizam entrada, validam tipos e reforçam vínculos hierárquicos.
- Policies expressam acesso por perfil, organização, responsabilidade e estado.
- Actions concentram escritas e usam transações onde há múltiplos registros.
- Services calculam snapshots, relatórios, cobertura, classificação e apresentação.
- Jobs processam imagens fora da requisição.

## Organização do código

- `app/Models`: entidades e relacionamentos Eloquent;
- `app/Enums`: estados e valores persistidos;
- `app/Http/Requests`: autorização inicial, normalização e validação;
- `app/Policies`: autorização por recurso;
- `app/Actions`: casos de uso com escrita;
- `app/Services`: regras reutilizáveis e read models;
- `app/Jobs`: processamento assíncrono de imagens;
- `resources/js/pages`: páginas Inertia;
- `resources/js/components`: UI e componentes de domínio;
- `tests/Feature` e `tests/Unit`: comportamento backend;
- `tests/js`: funções JavaScript independentes do navegador.

## Identidade e persistência

Entidades expostas em URLs usam `public_id` ULID por meio de `HasPublicId`; IDs
inteiros continuam sendo usados internamente e em chaves estrangeiras. Registros
históricos importantes preservam snapshots JSON para que alterações posteriores
no cadastro não reescrevam o contexto antigo.

Os principais registros operacionais repetem `organization_id`. Constraints e
índices compostos reforçam no banco os vínculos entre organização, pais e filhos.
Exclusões definitivas não são oferecidas para os principais cadastros; status e
soft delete preservam histórico conforme o recurso.

## Isolamento multiempresa

O tenant não vem de parâmetros enviados pelo frontend. Após autenticação,
`ResolveTenant` obtém a organização do usuário e preenche um `TenantContext`
scoped. O contexto é limpo em `finally` ao fim da requisição.

A defesa é composta por:

1. middlewares de conta, empresa, senha e tenant;
2. consultas `forOrganization(...)` ou filtros equivalentes;
3. route binding resolvido novamente dentro do tenant nos Controllers;
4. Policies que comparam `organization_id`;
5. validações e constraints compostas para relações hierárquicas.

Superadministradores não possuem tenant e recebem `403` ao tentar acessar módulos
operacionais.

## Autorização

- administradores da empresa mantêm configurações e cadastros do tenant;
- membros têm leitura operacional;
- escrita técnica de avarias, avaliações e mapas exige função de preparador e
  inspeção em `in_progress` ou `in_correction`;
- revisão, aprovação e liberação exigem a função correspondente;
- recursos de outro tenant não são aceitos, mesmo quando um ID válido é enviado.

## Arquivos e imagens

O filesystem possui três discos privados dedicados:

- `equipment_documents`;
- `inspection_photos`;
- `inspection_maps`.

Seus diretórios podem ser definidos por `EQUIPMENT_DOCUMENTS_ROOT`,
`INSPECTION_PHOTOS_ROOT` e `INSPECTION_MAPS_ROOT`. Downloads e visualizações são
servidos por Controllers após autorização; os arquivos privados não dependem de
uma URL pública direta.

Logos e ícones institucionais ficam no disco público. O symlink criado por
`php artisan storage:link` é necessário somente para esses assets públicos.

As filas usam Redis por padrão. `ProcessAssessmentPhoto`,
`ProcessInspectionOverviewPhoto` e `ProcessInspectionLocationMap` são enviados à
fila `images`, tentam três vezes, têm timeout de 180 segundos e backoff de 10, 60 e
300 segundos. O Supervisor do Horizon usa timeout de 210 segundos para permanecer
acima do timeout do Job.

## Frontend e relatório

As páginas recebem contratos explícitos do backend e capacidades já autorizadas.
Regras críticas não dependem de esconder botões no Vue.

A prévia do relatório é composta em páginas A4. O navegador captura cada página e
gera PDF ou DOCX; nenhum binário oficial é persistido no backend. Paginação de
sumário, aspectos gerais, mapas e documentação fotográfica é calculada no cliente
a partir do read model entregue pelo servidor.

## Concorrência e consistência

- criação de inspeções, sequências de avaria, posições e revisões críticas usam
  transações e bloqueios quando necessário;
- localizações de avaliação possuem `lock_version`; uma gravação obsoleta solicita que o
  usuário recarregue a página;
- Jobs de mapas publicam derivados somente na versão e no checksum esperados;
- dispatches de imagem ocorrem após o commit da transação;
- snapshots desacoplam histórico de alterações cadastrais posteriores.

## Qualidade

Comandos de verificação do projeto:

```bash
composer validate --strict --no-check-publish
vendor/bin/pint --test
php artisan test
npm run test:js
npm run build
git diff --check
```

A suíte padrão usa SQLite em memória. Testes que dependem de extensões de processo,
de migrations históricas ou de cenários demonstrativos removidos podem ser
ignorados com motivo explícito; isso não comprova o comportamento de um VPS real.
