# 02 — Arquitetura e Padrões

## 1. Objetivo

Definir a arquitetura técnica e os padrões obrigatórios do MVP antes da criação das tabelas e dos módulos operacionais.

Este documento existe para evitar:

- regras de negócio espalhadas em controllers;
- consultas sem filtro de organização;
- acoplamento excessivo entre Laravel, Vue e armazenamento;
- duplicação de lógica;
- geração de relatórios com dados inconsistentes;
- migrations difíceis de manter;
- uso prematuro de pacotes ou abstrações desnecessárias.

---

## 2. Stack aprovada

### Backend

- PHP 8.3 ou superior;
- Laravel 13;
- MySQL 8;
- autenticação por sessão;
- filas do Laravel;
- armazenamento pelo Laravel Filesystem.

### Frontend

- Inertia;
- Vue 3;
- TypeScript;
- Vite;
- interface responsiva e mobile-first.

### Infraestrutura

- desenvolvimento local com Laragon;
- produção futura em servidor dedicado Hetzner;
- banco de dados MySQL;
- armazenamento privado de arquivos;
- worker separado para filas em produção.

---

## 3. Estilo arquitetural

O sistema será um **monólito modular**.

Não serão criados microsserviços no MVP.

### Motivos

- o domínio ainda está sendo validado;
- os módulos possuem forte relacionamento;
- transações entre inspeções, avarias e avaliações precisam ser simples;
- um único time manterá o projeto;
- microsserviços aumentariam deploy, observabilidade, autenticação e consistência de dados sem benefício atual.

### Estrutura conceitual

```text
Aplicação Laravel
├── Administração da plataforma
├── Organizações e usuários
├── Clientes e estrutura operacional
├── Equipamentos
├── Inspeções
├── Avarias e avaliações
├── Fotografias
├── Classificação CIVIL
├── Revisão e auditoria
└── Relatórios
```

Os módulos serão separados por responsabilidade, mas permanecerão no mesmo código e banco.

---

## 4. Fluxo de uma requisição

O fluxo padrão será:

```text
Rota
↓
Middleware
↓
Form Request
↓
Policy
↓
Controller
↓
Action
↓
Model / Serviço de domínio
↓
Evento ou Job, quando necessário
↓
Resposta Inertia
```

### Regra central

Controllers devem coordenar a entrada e a saída HTTP.

Controllers não devem conter:

- cálculo GUT;
- geração de códigos;
- transições complexas de estado;
- processamento de imagens;
- geração de PDF;
- regras de reinspeção;
- consultas extensas;
- regras de autorização manuais repetidas.

---

## 5. Organização do backend

Estrutura inicial recomendada:

```text
app/
├── Actions/
│   ├── Organizations/
│   ├── Clients/
│   ├── Equipments/
│   ├── Inspections/
│   ├── Defects/
│   └── Reports/
├── Data/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Listeners/
├── Models/
├── Policies/
├── Providers/
├── Queries/
├── Services/
│   ├── Classification/
│   ├── Images/
│   ├── Reports/
│   └── Tenancy/
└── Support/
```

Não é obrigatório criar todas essas pastas imediatamente.

A pasta deve ser criada apenas quando houver uma classe real para armazenar.

---

## 6. Responsabilidade de cada camada

### 6.1 Models

Responsáveis por:

- relacionamentos Eloquent;
- casts;
- scopes locais simples;
- atributos derivados simples;
- métodos pequenos relacionados ao estado do próprio model.

Não devem concentrar todo o fluxo de um caso de uso.

Exemplo aceitável:

```php
public function isReleased(): bool
{
    return $this->status === InspectionStatus::Released;
}
```

Exemplo a evitar:

```php
public function finalizeInspectionAndGenerateReportAndNotify(): void
{
    // múltiplas responsabilidades
}
```

---

### 6.2 Form Requests

Responsáveis por:

- validação;
- normalização simples da entrada;
- mensagens de erro;
- autorização inicial da requisição, quando apropriado.

Cada operação de escrita relevante terá seu próprio Form Request.

Exemplos:

```text
StoreEquipmentRequest
UpdateEquipmentRequest
StoreInspectionRequest
SubmitInspectionForReviewRequest
StoreDefectAssessmentRequest
```

Não será usado `$request->validate()` diretamente em controllers de módulos relevantes.

---

### 6.3 Policies

Responsáveis por autorizar ações sobre recursos.

Exemplos:

```text
EquipmentPolicy
InspectionPolicy
DefectPolicy
ReportPolicy
```

As policies devem verificar:

- organização do usuário;
- organização do recurso;
- status do usuário;
- responsabilidade atribuída;
- estado atual da inspeção;
- tipo de conta.

Ocultar um botão no Vue não substitui uma Policy.

---

### 6.4 Actions

Uma Action representa um caso de uso com alteração de estado.

Exemplos:

```text
CreateEquipment
CreateInspection
AssignInspectionResponsible
StartInspection
SubmitInspectionForReview
ReturnInspectionForCorrection
ApproveInspection
ReleaseInspection
CreateDefect
AssessDefect
GenerateReport
```

Padrão:

```php
final class CreateInspection
{
    public function handle(User $actor, array $data): Inspection
    {
        return DB::transaction(function () use ($actor, $data) {
            // regra do caso de uso
        });
    }
}
```

Uma Action deve ter:

- nome orientado a ação;
- uma entrada clara;
- uma saída clara;
- transação quando altera múltiplos registros;
- autorização já validada pelo fluxo HTTP ou novamente validada quando chamada fora dele.

---

### 6.5 Services

Services serão usados para regras reutilizáveis ou integrações que não representam sozinhas uma ação do usuário.

Exemplos:

```text
GutCalculator
CivilClassificationResolver
DefectCodeGenerator
ImageProcessor
ReportRenderer
TenantContext
```

Diferença prática:

- `ApproveInspection` é uma Action;
- `GutCalculator` é um Service de domínio;
- `ProcessAssessmentPhoto` é um Job;
- `InspectionApproved` é um Event.

---

### 6.6 Queries

Classes de consulta serão usadas apenas para listagens ou relatórios complexos.

Exemplos:

```text
ListEquipmentInspections
ListOpenDefectsByEquipment
BuildCivilInspectionSummary
```

Não será criado um Repository genérico sobre Eloquent.

### Motivo

Um Repository genérico normalmente apenas repete métodos como:

```text
find
create
update
delete
```

Isso adicionaria camadas sem esconder uma fonte de dados variável ou uma integração real.

---

### 6.7 Data Objects

Objetos de dados imutáveis podem ser usados em casos de uso complexos.

Exemplo:

```php
final readonly class GutInput
{
    public function __construct(
        public int $gravity,
        public int $urgency,
        public int $trend,
    ) {}
}
```

Não devem ser criados DTOs para toda requisição simples.

---

### 6.8 Events e Listeners

Eventos representam fatos já ocorridos.

Exemplos:

```text
InspectionSubmittedForReview
InspectionApproved
InspectionReleased
ReportGenerated
```

Listeners poderão:

- registrar auditoria;
- enviar notificações;
- iniciar processamento secundário.

Regra:

> Eventos não devem esconder uma parte obrigatória da transação principal.

A inspeção só pode ser marcada como aprovada dentro da Action de aprovação.

---

### 6.9 Jobs

Jobs serão usados para tarefas demoradas ou reprocessáveis:

- processamento de fotografias;
- geração de miniaturas;
- geração do PDF;
- envio de notificações;
- limpeza de uploads temporários;
- importações futuras.

Todo Job relacionado a dados de uma organização deverá transportar explicitamente:

```text
organization_id
```

O Job não deve depender do usuário autenticado, pois workers não executam dentro de uma sessão HTTP.

---

## 7. Multiempresa

### 7.1 Estratégia

Será utilizado:

```text
um banco de dados
+
uma tabela de organizações
+
organization_id nas tabelas operacionais
```

Não será usado banco separado por organização no MVP.

---

### 7.2 Identificação da organização

Cada usuário comum pertence a uma única organização.

```text
users.organization_id
```

O superadministrador poderá possuir:

```text
organization_id = null
```

---

### 7.3 TenantContext

Será criado um serviço de contexto da organização atual:

```text
App\Services\Tenancy\TenantContext
```

Responsabilidades:

- armazenar a organização resolvida para a requisição atual;
- impedir operações sem contexto quando ele for obrigatório;
- permitir uso explícito em Jobs e comandos;
- evitar chamadas repetidas a `auth()->user()->organization_id`.

O binding deverá ter ciclo de vida por requisição ou Job.

Exemplo conceitual:

```php
final class TenantContext
{
    private ?int $organizationId = null;

    public function set(int $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function id(): int
    {
        return $this->organizationId
            ?? throw new LogicException('Tenant não definido.');
    }
}
```

---

### 7.4 Defesa em camadas

O isolamento não dependerá somente de Global Scope.

Serão utilizadas várias camadas:

1. Middleware resolve a organização.
2. TenantContext mantém a organização atual.
3. Policies validam o recurso.
4. Actions validam vínculos críticos.
5. Consultas aplicam `organization_id`.
6. Banco possui chaves estrangeiras e restrições compostas.
7. Testes tentam acessar dados de outra organização.
8. Jobs recebem `organization_id` explicitamente.

### Brecha evitada

Global Scope sozinho pode falhar em:

- Jobs;
- comandos Artisan;
- processos de relatório;
- consultas com `withoutGlobalScopes`;
- rotinas de superadministrador.

---

### 7.5 Criação de registros

O frontend nunca poderá escolher livremente `organization_id`.

O valor será definido no backend pelo contexto atual.

Exemplo:

```php
Equipment::create([
    'organization_id' => $tenant->id(),
    // demais campos validados
]);
```

---

## 8. Autenticação e autorização

### 8.1 Autenticação

O MVP usará autenticação por sessão, adequada ao monólito Laravel + Inertia.

Não será criada uma API tokenizada apenas para a interface web.

### 8.2 Tipos globais de conta

```text
super_admin
company_admin
member
```

### 8.3 Responsabilidades por inspeção

As responsabilidades técnicas serão atribuídas por inspeção:

```text
inspector
reviewer
approver
releaser
```

Elas não serão cargos fixos do usuário.

### 8.4 Usuários inativos

Um middleware deverá impedir acesso de usuários:

```text
inactive
suspended
```

### 8.5 Organização suspensa

Usuários comuns não poderão operar se a organização estiver suspensa.

---

## 9. Banco de dados

### 9.1 Convenções

- tabelas no plural e em `snake_case`;
- colunas em `snake_case`;
- chaves primárias numéricas `BIGINT UNSIGNED`;
- chaves estrangeiras terminadas em `_id`;
- timestamps padrão;
- nomes explícitos para índices compostos extensos;
- valores monetários e quantitativos decimais usando `DECIMAL`;
- nunca usar `FLOAT` para medidas que exigem precisão;
- documentos normalizados sem pontuação em coluna própria, quando necessário.

---

### 9.2 Identificadores públicos

Recursos expostos em URLs deverão possuir um identificador público não sequencial:

```text
public_id
```

Formato recomendado:

```text
ULID
```

Exemplo:

```text
equipments.id        = 148
equipments.public_id = 01K1...
```

Uso:

- `id` numérico para relacionamentos internos;
- `public_id` para rotas e exposição externa.

Não é necessário adicionar `public_id` a tabelas puramente internas, como tabelas pivot ou históricos técnicos sem rota própria.

---

### 9.3 Enums

No PHP serão usados enums nativos:

```text
InspectionStatus
DefectCondition
OrganizationStatus
UserStatus
ResponsibilityType
```

No MySQL serão usadas colunas `VARCHAR`, não `ENUM` nativo do banco.

### Motivo

Alterar um enum PHP e uma validação é mais simples e portátil que alterar o tipo `ENUM` da coluna em várias migrations e ambientes.

---

### 9.4 Integridade

Preferir:

```php
->restrictOnDelete()
```

para dados históricos e referências importantes.

Não usar `cascadeOnDelete()` em:

- organizações;
- clientes com histórico;
- equipamentos;
- inspeções;
- avarias;
- avaliações;
- relatórios.

Cascade poderá ser usado apenas em registros realmente dependentes e descartáveis, após análise específica.

---

### 9.5 Soft delete

Pode ser usado em cadastros que precisam sair das listagens sem perder histórico:

- organizações;
- clientes;
- unidades;
- áreas;
- subáreas;
- equipamentos, quando adequado.

Não usar soft delete como solução automática para tudo.

Para inspeções e relatórios, preferir estados de negócio e histórico de revisão.

---

### 9.6 Restrições compostas

Regras críticas devem existir também no banco.

Exemplos:

```text
organization_id + defect_code
organization_id + client_id + client_unit_id + normalized_tag
inspection_id + user_id + responsibility
```

Validação da aplicação não substitui índice único.

---

### 9.7 Snapshots

A inspeção deverá preservar dados do equipamento e da localização no momento da vistoria.

Exemplo:

```json
{
  "tag": "U03-06VT002",
  "equipment_name": "Ventilador",
  "client": "Samarco Mineração",
  "unit": "Ubu",
  "area": "Usina III",
  "subarea": "Forno de Endurecimento"
}
```

Snapshots poderão ser armazenados em JSON, mas os campos usados em filtros frequentes deverão permanecer em colunas normais.

---

### 9.8 Datas e fuso horário

Datas e horários técnicos serão armazenados em UTC.

Cada organização poderá ter seu fuso de exibição.

Valor inicial sugerido:

```text
America/Sao_Paulo
```

Datas de inspeção sem horário serão armazenadas como `DATE`.

---

## 10. Transações e concorrência

### 10.1 Transações

Usar `DB::transaction()` quando uma operação alterar múltiplos registros.

Exemplos:

- criar inspeção e responsáveis;
- criar avaria e primeira avaliação;
- aprovar inspeção e registrar histórico;
- liberar revisão e congelar snapshot.

### 10.2 Bloqueio

Transições sensíveis poderão usar:

```php
lockForUpdate()
```

para impedir duas aprovações ou liberações simultâneas.

### 10.3 Idempotência

Jobs reprocessáveis deverão evitar duplicação.

Exemplos:

- não gerar dois PDFs para a mesma revisão;
- não criar duas miniaturas para a mesma versão da foto;
- não enviar duas notificações do mesmo evento.

---

## 11. Máquina de estados

Estados de inspeção serão controlados por regras explícitas.

Exemplo:

```text
planned
in_progress
awaiting_review
in_correction
awaiting_approval
approved
report_generated
released
```

Não será permitido alterar livremente o status por um formulário genérico.

Cada transição terá sua própria Action.

Exemplo:

```text
StartInspection
SubmitInspectionForReview
ReturnInspectionForCorrection
ApproveInspection
MarkReportAsGenerated
ReleaseInspection
```

Isso evita transições inválidas, como:

```text
planned → released
```

---

## 12. Auditoria

A auditoria deverá registrar ações de negócio relevantes:

- criação;
- alteração;
- envio para revisão;
- devolução;
- aprovação;
- liberação;
- alteração de responsável;
- alteração após reabertura;
- geração de relatório.

Estrutura conceitual:

```text
audit_logs
- organization_id
- actor_id
- auditable_type
- auditable_id
- action
- old_values
- new_values
- metadata
- ip_address
- user_agent
- created_at
```

### Regra

Senhas, tokens e dados secretos não podem ser gravados em auditoria.

---

## 13. Armazenamento de arquivos

### 13.1 Abstração

Todo acesso a arquivos será feito pelo Laravel Filesystem.

Nenhum caminho físico fixo deverá aparecer em regra de negócio.

### 13.2 Discos

Sugestão:

```text
local_private      → desenvolvimento
inspection_files   → produção
temporary_uploads  → uploads ainda não confirmados
```

O disco de produção poderá ser local privado ou compatível com S3, sem alterar o domínio.

### 13.3 Privacidade

Fotos e relatórios não ficarão em diretório público permanente.

O acesso será autorizado e temporário.

### 13.4 Metadados

O banco armazenará:

- disk;
- path;
- original_name;
- mime_type;
- extension;
- size;
- width;
- height;
- checksum;
- processing_status;
- uploaded_by;
- captured_at.

---

## 14. Processamento de imagens

Fluxo previsto:

```text
Celular seleciona ou captura foto
↓
Validação inicial no navegador
↓
Upload temporário
↓
Registro pendente no banco
↓
Job de normalização
↓
Correção de orientação
↓
Redimensionamento
↓
Compressão
↓
Miniatura
↓
Arquivo final privado
↓
Registro marcado como pronto
```

Uma inspeção não poderá ser enviada para revisão enquanto houver foto:

```text
pending
processing
failed
```

---

## 15. Filas

Filas serão usadas desde o MVP para:

- processamento de imagens;
- geração de PDF;
- notificações;
- tarefas de limpeza.

### Desenvolvimento

Pode usar driver `database`.

### Produção

A arquitetura deverá permitir migração para Redis.

### Filas sugeridas

```text
default
images
reports
notifications
```

### Regras dos Jobs

Todo Job deverá definir, conforme necessidade:

- número máximo de tentativas;
- timeout;
- backoff;
- tratamento de falha;
- idempotência;
- `organization_id`;
- identificadores simples, evitando objetos excessivos no payload.

---

## 16. Relatórios

A geração do PDF será separada em três partes:

```text
BuildReportData
↓
RenderReportHtml
↓
GenerateReportPdf
```

### Regras

- o relatório usa uma revisão aprovada;
- o conjunto de dados é congelado;
- geração ocorre em fila;
- o PDF final recebe checksum;
- o arquivo é privado;
- uma nova alteração gera nova revisão;
- o relatório antigo permanece disponível.

---

## 17. Organização do frontend

Estrutura recomendada:

```text
resources/js/
├── components/
│   ├── ui/
│   ├── forms/
│   └── domain/
├── composables/
├── layouts/
├── pages/
│   ├── Organizations/
│   ├── Users/
│   ├── Clients/
│   ├── Equipments/
│   ├── Inspections/
│   ├── Defects/
│   └── Reports/
├── types/
└── utils/
```

### Regras

- páginas organizadas por domínio;
- componentes genéricos em `components/ui`;
- componentes de negócio em `components/domain`;
- tipos compartilhados em `types`;
- não duplicar regras de negócio no frontend;
- não confiar em validação apenas no navegador;
- não usar URLs escritas manualmente;
- manter componentes pequenos e focados.

---

## 18. Estado no frontend

A fonte principal dos dados será:

```text
Laravel + props do Inertia
```

Não será instalado Pinia apenas para replicar dados do servidor.

Pinia só será considerado se surgir estado global real, por exemplo:

- fila global de uploads;
- modo de inspeção com rascunho complexo;
- preferências persistentes da interface.

Formulários usarão os recursos de formulário do Inertia ou uma abstração fina sobre eles.

---

## 19. API

O MVP não terá uma API REST paralela para todas as operações.

A interface web usará rotas Laravel + Inertia.

### Motivo

Criar simultaneamente:

- controllers Inertia;
- controllers de API;
- resources duplicados;
- autenticação por token;

aumentaria o trabalho sem um consumidor real.

A lógica permanecerá em Actions e Services para permitir uma API futura sem reescrever as regras.

---

## 20. Respostas e serialização

Controllers Inertia devem enviar somente os dados necessários para a página.

Evitar:

```php
return Inertia::render('Equipments/Show', [
    'equipment' => $equipment->load('*'),
]);
```

Preferir:

- seleção explícita de colunas;
- eager loading controlado;
- Resources ou mapeadores;
- paginação;
- dados derivados prontos quando apropriado.

Isso reduz:

- vazamento acidental;
- payload;
- consultas N+1;
- acoplamento da interface ao banco.

---

## 21. Validação e normalização

### Regras gerais

- TAG normalizado no backend;
- documento armazenado sem máscara;
- e-mail em minúsculas;
- strings com trim;
- medidas com precisão definida;
- arquivos validados por conteúdo e MIME;
- limites de tamanho definidos no backend;
- validações dependentes do tenant usando constraints explícitas.

Exemplo de unicidade:

```text
TAG único por organização + cliente + unidade
```

Nunca validar TAG como único globalmente.

---

## 22. Exceções

Exceções de domínio poderão representar violações previsíveis:

```text
InvalidInspectionTransition
InspectionHasPendingPhotos
DefectCodeAlreadyExists
CrossTenantAccessAttempt
ReportRevisionNotApproved
```

Elas devem ser convertidas em respostas claras ao usuário.

Não usar exceções para fluxo comum que pode ser tratado por validação simples.

---

## 23. Logs

Logs técnicos devem conter contexto útil:

- organization_id;
- user_id;
- inspection_id;
- job_id;
- report_revision_id;
- ação executada.

Não registrar:

- senha;
- token;
- conteúdo integral de documentos sensíveis;
- binário ou base64 de fotografia.

---

## 24. Testes

O projeto deverá usar um único estilo de testes de forma consistente.

Para este projeto, será adotado:

```text
PHPUnit
```

### 24.1 Feature tests

Prioridade principal.

Cobrir:

- autenticação;
- autorização;
- isolamento entre organizações;
- criação e edição;
- transições da inspeção;
- reinspeção;
- código permanente da avaria;
- upload;
- aprovação;
- geração de relatório.

### 24.2 Unit tests

Usados para regras puras:

- cálculo GUT;
- resolução da classificação CV;
- normalização de TAG;
- geração de código;
- transições permitidas.

### 24.3 Testes obrigatórios de multiempresa

Para cada recurso operacional relevante:

```text
usuário da Organização A
não pode visualizar
não pode alterar
não pode excluir
não pode relacionar
um recurso da Organização B
```

### 24.4 Banco de testes

Os testes devem refletir o comportamento do MySQL nas regras críticas.

SQLite pode divergir em:

- constraints;
- collation;
- JSON;
- índices;
- tipos de dados.

A estratégia final de banco de testes será definida em `12-TESTES-E-SEGURANCA.md`.

---

## 25. Factories e seeders

Cada model de domínio terá Factory quando isso facilitar testes.

Seeders serão separados por finalidade:

```text
DevelopmentSeeder
DemoOrganizationSeeder
CivilClassificationSeeder
```

O seeder principal não deve criar credenciais previsíveis em produção.

---

## 26. Qualidade de código

### Ferramentas

- Laravel Pint;
- PHPUnit;
- análise estática a ser avaliada após a fundação do MVP.

### Regras

Antes de cada commit de etapa:

```bash
vendor/bin/pint --dirty
php artisan test
npm run build
```

### Padrões

- `declare(strict_types=1);` nos novos arquivos PHP de domínio;
- classes `final` quando não há intenção de herança;
- tipos de retorno explícitos;
- propriedades tipadas;
- imports organizados;
- nomes orientados ao domínio;
- comentários somente quando explicam uma decisão, não o código óbvio.

---

## 27. Dependências externas

Regra:

> Não instalar um pacote quando o framework resolve o requisito de forma simples e segura.

Antes de adicionar pacote, registrar:

- problema resolvido;
- alternativas nativas;
- manutenção do pacote;
- compatibilidade;
- impacto no domínio;
- estratégia de remoção.

### Decisões iniciais

Não instalar agora:

- pacote completo de multi-tenancy;
- repository package;
- state machine package;
- pacote de permissões para responsabilidades por inspeção;
- pacote de auditoria antes de definir os eventos auditáveis;
- pacote de PDF antes do documento específico de relatórios.

---

## 28. Rotas

Rotas serão organizadas por domínio e protegidas por middleware.

Exemplo conceitual:

```php
Route::middleware([
    'auth',
    'verified',
    'user.active',
    'organization.active',
    'tenant',
])->group(function () {
    // rotas operacionais
});
```

### Padrões

- nomes de rota;
- route model binding por `public_id`;
- resources apenas quando o fluxo for realmente CRUD;
- Actions específicas para transições.

Exemplo:

```text
POST /inspections/{inspection}/submit-for-review
POST /inspections/{inspection}/approve
POST /inspections/{inspection}/release
```

Não usar:

```text
PATCH /inspections/{inspection}
status=released
```

para burlar a máquina de estados.

---

## 29. Controle de versão

### Commits

Padrão sugerido:

```text
feat:
fix:
docs:
test:
refactor:
chore:
```

Exemplos:

```text
feat: add organization tenant foundation
test: prevent cross-tenant equipment access
docs: document inspection state transitions
```

### Regra

Uma mudança de regra deve atualizar no mesmo commit:

- código;
- testes;
- documento correspondente.

---

## 30. Decisões adiadas

Serão decididas nos documentos específicos:

- biblioteca de processamento de imagem;
- mecanismo final de geração de PDF;
- armazenamento local versus objeto compatível com S3;
- estratégia de upload direto;
- Redis e Horizon em produção;
- biblioteca de componentes visuais;
- importação de planilhas históricas;
- assinatura eletrônica;
- política de retenção de arquivos.

---

## 31. Critérios de aceite deste documento

**Estado: Documentado.** As marcações abaixo registram decisões arquiteturais descritas, não a comprovação de que cada padrão foi implementado. Migrations, suíte automatizada, build e validação manual permanecem **não validados nesta conferência**.

- [x] estilo arquitetural definido;
- [x] fluxo de requisição definido;
- [x] responsabilidades das camadas definidas;
- [x] estratégia multiempresa definida;
- [x] autenticação e autorização definidas;
- [x] padrões de banco definidos;
- [x] filas e Jobs definidos;
- [x] armazenamento definido conceitualmente;
- [x] organização do frontend definida;
- [x] estratégia de testes definida;
- [x] regras de dependências definidas;
- [x] padrões de rotas e commits definidos.

---

## 32. Riscos e brechas

### 32.1 Overengineering

Criar todas as pastas, interfaces e abstrações antes de existirem casos reais tornaria o projeto lento.

Mitigação:

- criar abstrações sob demanda;
- manter Actions pequenas;
- evitar Repository genérico;
- não criar microsserviços.

### 32.2 Tenant implícito

Depender apenas do usuário autenticado pode falhar em Jobs e comandos.

Mitigação:

- TenantContext;
- `organization_id` explícito;
- testes fora de requisições HTTP.

### 32.3 Regra duplicada no Vue

Calcular classificação ou permitir transições apenas no frontend abre brecha de segurança e inconsistência.

Mitigação:

- backend como fonte de verdade;
- frontend apenas apresenta previsão e feedback.

### 32.4 Eventos escondendo consistência

Colocar ações essenciais em listeners pode deixar a inspeção parcialmente processada.

Mitigação:

- transação principal dentro da Action;
- eventos somente após o estado obrigatório estar consistente.

### 32.5 Soft delete indiscriminado

Soft delete em todas as tabelas aumenta complexidade de unicidade, consultas e histórico.

Mitigação:

- usar apenas onde existe caso real de restauração;
- preferir status de negócio em registros técnicos.

---

## 33. Próximo documento

O encadeamento documental aponta para o documento 03, sem declarar concluída a implementação ou a validação executável da arquitetura.

```text
03-MODELAGEM-MULTIEMPRESA.md
```

O próximo documento definirá e implementará:

- `organizations`;
- alterações em `users`;
- tipos de conta;
- status;
- TenantContext;
- middlewares;
- Policies iniciais;
- seed de desenvolvimento;
- testes de isolamento;
- critérios de aceite e commit.

---

## 34. Referências oficiais

- Laravel 13 — Service Container: https://laravel.com/docs/13.x/container
- Laravel 13 — Service Providers: https://laravel.com/docs/13.x/providers
- Laravel 13 — Authentication: https://laravel.com/docs/13.x/authentication
- Laravel 13 — Authorization: https://laravel.com/docs/13.x/authorization
- Laravel 13 — Validation: https://laravel.com/docs/13.x/validation
- Laravel 13 — Queues: https://laravel.com/docs/13.x/queues
- Laravel 13 — File Storage: https://laravel.com/docs/13.x/filesystem
- Laravel 13 — Testing: https://laravel.com/docs/13.x/testing
- Laravel 13 — Database Testing: https://laravel.com/docs/13.x/database-testing
- Inertia — Laravel server-side setup: https://inertiajs.com/docs/v3/installation/server-side-setup
- Inertia — Client-side setup: https://inertiajs.com/docs/v3/installation/client-side-setup

---

## 35. Commit sugerido

```bash
git add docs/02-ARQUITETURA-E-PADROES.md
git commit -m "docs: define arquitetura e padrões do MVP"
```
