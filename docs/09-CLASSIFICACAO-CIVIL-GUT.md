# 09 — Classificação CIVIL e GUT

## 1. Objetivo

Implementar a avaliação técnica das avarias CIVIL, incluindo:

- gravidade;
- urgência;
- tendência;
- pontuação GUT;
- classificação CV;
- prazo recomendado;
- tipo de dano;
- elemento afetado;
- impacto na atividade;
- quantitativos;
- comentários;
- recomendações;
- resumo de criticidade do equipamento.

A regra central será:

```text
GUT = Gravidade × Urgência × Tendência
```

A classificação `CV` será resolvida por uma versão de regras vinculada à inspeção.

Não será codificada diretamente em `if/else` permanente.

---

## 2. Base técnica analisada

O relatório técnico de referência confirma o uso de:

```text
G
U
T
Pontuação GUT
Classificação CV
Tipo de dano
Item / subitem
Elemento
Quantidade
Quantitativo
Impacto na atividade
Comentário
Recomendação
```

Exemplos observados no relatório:

```text
3 × 4 × 3 = 36 → CV-2
3 × 5 × 2 = 30 → CV-3
3 × 4 × 2 = 24 → CV-3
3 × 3 × 3 = 27 → CV-3
3 × 4 × 1 = 12 → CV-4
3 × 5 × 1 = 15 → CV-4
3 × 1 × 2 = 6  → CV-5
```

O resumo do equipamento utiliza a classificação mais crítica entre as avarias.

Também foram observados os códigos:

```text
CV-0
CV-1
CV-2
CV-3
CV-4
CV-5
```

---

## 3. Limite técnico conhecido

O relatório cita o procedimento:

```text
T000000-S-2PO006_R-04
```

Esse procedimento não está disponível entre os documentos analisados.

Portanto, ainda não estão confirmados:

- descrições oficiais de cada nota G;
- descrições oficiais de cada nota U;
- descrições oficiais de cada nota T;
- faixas completas de CV-0 a CV-5;
- prazo oficial de CV-1;
- todas as regras de datas;
- exceções previstas no procedimento.

### Decisão

A arquitetura será implementada agora.

Os valores oficiais só poderão ser ativados em produção depois da validação do procedimento.

Dados observados no relatório poderão ser usados em ambiente de demonstração, marcados como provisórios.

---

## 4. Resultado esperado

Ao concluir esta etapa, o sistema deverá permitir:

- selecionar um perfil de classificação para a inspeção;
- informar notas G, U e T;
- calcular automaticamente a pontuação;
- resolver automaticamente a classificação CV;
- calcular o prazo recomendado quando configurado;
- cadastrar tipos de dano;
- cadastrar elementos CIVIL;
- registrar item ou subitem observado;
- registrar impacto na atividade;
- registrar quantitativos com unidade;
- sugerir comentários;
- sugerir recomendações;
- permitir edição técnica do texto sugerido;
- preservar a versão das regras usadas;
- recalcular enquanto a avaliação estiver editável;
- bloquear alteração silenciosa após revisão;
- calcular a criticidade geral do equipamento;
- produzir resumo por classificação;
- validar avaliações antes do envio para revisão.

---

## 5. Escopo incluído

Será criado:

- perfis versionados de classificação;
- critérios G, U e T;
- faixas de classificação CV;
- prazo por faixa;
- tipos de dano CIVIL;
- elementos CIVIL;
- modelos de comentário;
- modelos de recomendação;
- quantitativos;
- cálculo GUT;
- resolução CV;
- snapshot da regra aplicada;
- vínculo do perfil com a inspeção;
- campos técnicos na avaliação;
- Actions;
- Services;
- Queries;
- Policies;
- Form Requests;
- Controllers;
- páginas Vue;
- seed técnico;
- testes.

---

## 6. Fora do escopo

Não será implementado agora:

- TAC;
- REC;
- telhados e tapamentos laterais;
- classificação automática por inteligência artificial;
- interpretação automática de fotografia;
- importação automática das macros existentes;
- localização gráfica no desenho;
- cálculo de peso de perfis;
- integração SAP;
- criação automática de nota SAP;
- aprovação técnica das regras pelo sistema;
- edição das regras por qualquer membro comum.

---

# 7. Conceitos do domínio

## 7.1 Perfil de classificação

Representa um procedimento técnico específico.

Exemplo:

```text
Samarco — Priorização de Avarias
Procedimento: T000000-S-2PO006
Revisão: 04
Versão interna: 1
```

Um perfil define:

- critérios G;
- critérios U;
- critérios T;
- faixas CV;
- prazos;
- tipos de dano;
- elementos;
- modelos de texto.

---

## 7.2 Versão

Cada alteração de regra cria nova versão.

Exemplo:

```text
Revisão 04 → perfil versão 1
Revisão 05 → perfil versão 2
```

Uma inspeção antiga continua vinculada à versão usada quando foi criada.

---

## 7.3 Pontuação GUT

```text
gut_score = gravity × urgency × trend
```

As notas deverão ser inteiras dentro da escala definida pelo perfil.

No MVP, a escala será:

```text
1 a 5
```

---

## 7.4 Classificação CV

A classificação será encontrada pela faixa que contém a pontuação.

Exemplo conceitual:

```text
min_score ≤ gut_score ≤ max_score
```

A classe também terá:

```text
priority_order
```

Quanto menor o `priority_order`, mais crítica.

Não inferir criticidade apenas pelo texto `CV-1`, `CV-2` etc.

---

## 7.5 Prazo recomendado

Uma faixa poderá possuir:

```text
deadline_months
```

Exemplo observado:

```text
CV-2 → 24 meses
CV-3 → 36 meses
CV-4 → N/A
CV-5 → N/A
```

Esses valores são exemplos do relatório e precisam de confirmação no procedimento.

---

# 8. Regras de negócio

## 8.1 Perfil obrigatório

Uma inspeção CIVIL deve possuir um perfil ativo de classificação antes de ser iniciada.

---

## 8.2 Perfil congelado

Depois que a inspeção entrar em:

```text
in_progress
```

o perfil não poderá ser trocado por edição comum.

---

## 8.3 Snapshot da regra

Cada avaliação classificada deverá armazenar:

```text
profile_id
profile_version
gravity
urgency
trend
gut_score
classification_code
classification_priority
deadline_months
recommended_due_date
classification_snapshot
```

O snapshot impedirá que uma alteração futura no cadastro modifique o histórico.

---

## 8.4 Cálculo no backend

O frontend poderá exibir uma prévia.

A fonte de verdade será o backend.

---

## 8.5 Reavaliação

Em uma reinspeção, os valores G, U e T anteriores serão mostrados apenas como referência.

O inspetor deverá informar uma nova avaliação.

---

## 8.6 Condições que exigem classificação

Exigem GUT:

```text
new
unchanged
worsened
improved
```

Não recebem nova classificação ativa:

```text
repaired
not_located
not_inspected
```

### Justificativa

Uma avaria reparada não deve continuar aparecendo como dano ativo `CV-2` ou `CV-3`.

A classificação anterior permanece no histórico.

---

## 8.7 Avaliação reparada

Exige:

- condição `repaired`;
- evidência fotográfica;
- comentário;
- recomendação ou conclusão do reparo;
- GUT atual nulo;
- classificação atual nula.

---

## 8.8 Não localizada ou não inspecionada

Exige:

- justificativa;
- GUT nulo;
- classificação nula;
- avaria permanece ativa.

---

## 8.9 Classificação do equipamento

A criticidade CIVIL do equipamento em uma inspeção será a classificação mais crítica entre avaliações ativas e completas.

Não considerar:

```text
repaired
not_located
not_inspected
```

como uma nova classificação ativa.

---

## 8.10 Quantidade de avarias

O resumo por classe deverá calcular:

```text
quantidade de avaliações completas por classificação
```

---

## 8.11 Quantitativos

Uma avaliação poderá possuir um ou mais quantitativos.

Exemplos:

```text
1,20 m²
4,00 m²
3 unidades
0,40 m³
```

Não fixar CIVIL exclusivamente em `m²` ou `m³`.

---

## 8.12 Totalização

Só somar quantitativos com a mesma unidade.

Exemplo:

```text
m² + m² → permitido
m² + unidade → resumos separados
```

---

## 8.13 Comentários e recomendações

Modelos serão sugestões.

O texto final poderá ser editado pelo responsável técnico.

O relatório usará o texto final armazenado na avaliação.

---

## 8.14 Modelo não substitui responsabilidade técnica

Selecionar um tipo de dano não deve publicar automaticamente uma conclusão sem revisão humana.

---

# 9. Estrutura de dados

Serão criadas:

```text
classification_profiles
gut_criterion_options
civil_classification_ranges
civil_damage_types
civil_structural_elements
civil_comment_templates
civil_recommendation_templates
defect_assessment_quantities
```

E serão alteradas:

```text
inspections
defect_assessments
```

---

# 10. Enums

## 10.1 `GutCriterion.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum GutCriterion: string
{
    case Gravity = 'gravity';
    case Urgency = 'urgency';
    case Trend = 'trend';
}
```

---

## 10.2 `ClassificationProfileStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ClassificationProfileStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';
}
```

---

## 10.3 `MeasurementUnit.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum MeasurementUnit: string
{
    case Unit = 'unit';
    case Meter = 'm';
    case SquareMeter = 'm2';
    case CubicMeter = 'm3';
    case Millimeter = 'mm';
    case Centimeter = 'cm';
    case Kilogram = 'kg';
    case Liter = 'l';
    case Other = 'other';
}
```

---

# 11. Migration `classification_profiles`

```php
Schema::create('classification_profiles', function (Blueprint $table): void {
    $table->id();
    $table->ulid('public_id')->unique();

    $table->foreignId('organization_id')
        ->constrained()
        ->restrictOnDelete();

    $table->foreignId('client_id')
        ->nullable()
        ->constrained()
        ->restrictOnDelete();

    $table->string('name', 180);
    $table->string('category', 30)->default('civil');

    $table->string('procedure_number', 150)->nullable();
    $table->string('procedure_revision', 50)->nullable();
    $table->unsignedInteger('version');

    $table->string('status', 20)->default('draft');
    $table->date('effective_from')->nullable();
    $table->date('effective_until')->nullable();

    $table->text('notes')->nullable();

    $table->foreignId('created_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->timestamps();

    $table->unique(
        ['organization_id', 'client_id', 'name', 'version'],
        'classification_profiles_scope_version_unique',
    );

    $table->index(
        ['organization_id', 'client_id', 'status'],
        'classification_profiles_scope_status_index',
    );
});
```

---

# 12. Migration `gut_criterion_options`

```php
Schema::create('gut_criterion_options', function (Blueprint $table): void {
    $table->id();

    $table->foreignId('classification_profile_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('criterion', 20);
    $table->unsignedTinyInteger('score');

    $table->string('label', 150);
    $table->text('description');
    $table->unsignedTinyInteger('position');

    $table->timestamps();

    $table->unique(
        ['classification_profile_id', 'criterion', 'score'],
        'gut_options_profile_criterion_score_unique',
    );
});
```

### Observação

O `cascadeOnDelete()` é aceitável enquanto o perfil estiver em rascunho.

Um perfil ativo não poderá ser apagado pela aplicação.

---

# 13. Migration `civil_classification_ranges`

```php
Schema::create('civil_classification_ranges', function (Blueprint $table): void {
    $table->id();

    $table->foreignId('classification_profile_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('code', 20);
    $table->string('label', 150);

    $table->unsignedInteger('min_score')->nullable();
    $table->unsignedInteger('max_score')->nullable();

    $table->unsignedTinyInteger('priority_order');
    $table->unsignedInteger('deadline_months')->nullable();

    $table->text('description')->nullable();

    $table->timestamps();

    $table->unique(
        ['classification_profile_id', 'code'],
        'civil_ranges_profile_code_unique',
    );

    $table->unique(
        ['classification_profile_id', 'priority_order'],
        'civil_ranges_profile_priority_unique',
    );
});
```

---

# 14. Cadastros CIVIL

## 14.1 `civil_damage_types`

Campos:

```text
organization_id
classification_profile_id nullable
code
name
description
status
position
```

Exemplos observados:

```text
INFILTRAÇÃO
SEGREGAÇÃO E DESAGREGAÇÃO
FISSURAÇÃO
```

---

## 14.2 `civil_structural_elements`

Campos:

```text
organization_id
classification_profile_id nullable
code
name
description
status
position
```

Exemplos observados:

```text
BASES DE SUSTENTAÇÃO DE EQUIPAMENTOS
PAREDES DE ALVENARIA, SEM FINALIDADE ESTRUTURAL
```

---

## 14.3 Item ou subitem

O campo observado, como:

```text
LAJE
ESTRUTURA
ESCADA
BASE DO MOTOR
```

será armazenado diretamente na avaliação como:

```text
item_description
```

Poderá virar cadastro próprio depois, caso a repetição justifique.

---

# 15. Modelos de texto

## 15.1 `civil_comment_templates`

Campos:

```text
organization_id
classification_profile_id nullable
damage_type_id nullable
structural_element_id nullable
title
template_text
status
position
```

---

## 15.2 `civil_recommendation_templates`

Mesma estrutura.

### Regra

O texto copiado para a avaliação torna-se independente do modelo.

Alterar o modelo não modifica avaliações anteriores.

---

# 16. Quantitativos

Migration:

```php
Schema::create('defect_assessment_quantities', function (Blueprint $table): void {
    $table->id();

    $table->foreignId('organization_id')
        ->constrained()
        ->restrictOnDelete();

    $table->unsignedBigInteger('defect_assessment_id');

    $table->string('description', 180)->nullable();
    $table->decimal('quantity', 14, 4)->default(1);
    $table->decimal('measurement_value', 16, 4)->nullable();
    $table->string('measurement_unit', 20);
    $table->unsignedInteger('position')->default(1);
    $table->text('notes')->nullable();

    $table->timestamps();

    $table->foreign(
        ['organization_id', 'defect_assessment_id'],
        'assessment_quantities_org_assessment_foreign',
    )
        ->references(['organization_id', 'id'])
        ->on('defect_assessments')
        ->restrictOnDelete();

    $table->index(
        ['organization_id', 'defect_assessment_id', 'position'],
        'assessment_quantities_org_assessment_position_index',
    );
});
```

### Ajuste necessário

Adicionar em `defect_assessments`:

```php
$table->unique(
    ['organization_id', 'id'],
    'defect_assessments_org_id_unique',
);
```

---

# 17. Alterar `inspections`

Adicionar:

```text
classification_profile_id
classification_profile_snapshot
```

Migration conceitual:

```php
$table->foreignId('classification_profile_id')
    ->nullable()
    ->constrained()
    ->restrictOnDelete();

$table->json('classification_profile_snapshot')->nullable();
```

### Regra

Antes de iniciar uma inspeção CIVIL:

```text
classification_profile_id obrigatório
```

---

# 18. Alterar `defect_assessments`

Adicionar:

```text
damage_type_id
structural_element_id
comment_template_id
recommendation_template_id
item_description
project_reference
impacts_activity
gravity
urgency
trend
gut_score
classification_code
classification_priority
deadline_months
recommended_due_date
classification_snapshot
classified_at
classified_by
```

Migration conceitual:

```php
$table->foreignId('damage_type_id')
    ->nullable()
    ->constrained('civil_damage_types')
    ->restrictOnDelete();

$table->foreignId('structural_element_id')
    ->nullable()
    ->constrained('civil_structural_elements')
    ->restrictOnDelete();

$table->foreignId('comment_template_id')
    ->nullable()
    ->constrained('civil_comment_templates')
    ->nullOnDelete();

$table->foreignId('recommendation_template_id')
    ->nullable()
    ->constrained('civil_recommendation_templates')
    ->nullOnDelete();

$table->string('item_description', 180)->nullable();
$table->string('project_reference', 180)->nullable();
$table->boolean('impacts_activity')->nullable();

$table->unsignedTinyInteger('gravity')->nullable();
$table->unsignedTinyInteger('urgency')->nullable();
$table->unsignedTinyInteger('trend')->nullable();

$table->unsignedInteger('gut_score')->nullable();
$table->string('classification_code', 20)->nullable();
$table->unsignedTinyInteger('classification_priority')->nullable();

$table->unsignedInteger('deadline_months')->nullable();
$table->date('recommended_due_date')->nullable();

$table->json('classification_snapshot')->nullable();

$table->timestamp('classified_at')->nullable();

$table->foreignId('classified_by')
    ->nullable()
    ->constrained('users')
    ->nullOnDelete();
```

---

# 19. Serviço `GutCalculator`

```php
<?php

declare(strict_types=1);

namespace App\Services\Classification;

use InvalidArgumentException;

final class GutCalculator
{
    public function calculate(
        int $gravity,
        int $urgency,
        int $trend,
    ): int {
        foreach ([$gravity, $urgency, $trend] as $score) {
            if ($score < 1 || $score > 5) {
                throw new InvalidArgumentException(
                    'As notas GUT devem estar entre 1 e 5.',
                );
            }
        }

        return $gravity * $urgency * $trend;
    }
}
```

---

# 20. Serviço `CivilClassificationResolver`

Responsabilidades:

- receber perfil;
- receber pontuação;
- localizar exatamente uma faixa;
- rejeitar sobreposição;
- rejeitar lacuna;
- retornar código, prioridade e prazo.

Estrutura:

```php
final readonly class CivilClassificationResult
{
    public function __construct(
        public string $code,
        public int $priorityOrder,
        public ?int $deadlineMonths,
        public array $snapshot,
    ) {}
}
```

---

# 21. Validador de perfil

Criar:

```text
ClassificationProfileValidator
```

Antes de ativar um perfil, validar:

- notas 1 a 5 para G;
- notas 1 a 5 para U;
- notas 1 a 5 para T;
- sem nota duplicada;
- faixas sem sobreposição;
- faixas sem lacunas dentro do intervalo usado;
- códigos únicos;
- prioridades únicas;
- pelo menos CV-1 a CV-5 ou justificativa;
- procedimento e revisão;
- cliente correto;
- perfil ainda não usado.

---

# 22. Action `ApplyCivilClassification`

Entrada:

```text
assessment
gravity
urgency
trend
damage_type
structural_element
item_description
project_reference
impacts_activity
quantities
comment
recommendation
```

Responsabilidades:

1. validar tenant;
2. validar inspeção editável;
3. validar perfil;
4. validar condição;
5. calcular GUT;
6. resolver CV;
7. calcular prazo;
8. gravar snapshot;
9. sincronizar quantitativos;
10. registrar usuário;
11. marcar avaliação completa quando todos os requisitos forem atendidos.

Tudo dentro de transação.

---

# 23. Prazo recomendado

Cálculo:

```text
recommended_due_date
=
inspection.inspected_on
+
deadline_months
```

Se:

```text
deadline_months = null
```

então:

```text
recommended_due_date = null
```

O relatório mostrará:

```text
N/A
```

---

# 24. Perfil da inspeção

Criar Action:

```text
AssignClassificationProfileToInspection
```

Permitida apenas quando:

```text
inspection.status = planned
```

Regras:

- perfil ativo;
- mesmo tenant;
- cliente compatível;
- categoria CIVIL;
- vigência compatível;
- snapshot salvo.

---

# 25. Templates

Criar Actions:

```text
ApplyCommentTemplate
ApplyRecommendationTemplate
```

Essas Actions apenas retornam ou copiam uma sugestão.

Não devem bloquear edição manual.

---

# 26. Resumo da inspeção

Criar Query:

```text
BuildCivilClassificationSummary
```

Saída:

```json
{
  "most_critical": "CV-2",
  "classes": [
    {
      "code": "CV-2",
      "count": 1,
      "due_date": "2028-05-11",
      "totals": {
        "m2": 1.20
      }
    }
  ]
}
```

---

# 27. Criticidade do equipamento

Criar serviço:

```text
ResolveEquipmentCivilCriticality
```

Regra:

- considerar apenas avaliação completa;
- considerar apenas classificação ativa;
- ordenar por `classification_priority`;
- retornar a mais crítica;
- não recalcular relatório antigo a partir de regras atuais.

---

# 28. Validação antes da revisão

Criar:

```text
CivilAssessmentCoverageValidator
```

Antes de enviar para revisão, validar em cada avaliação aplicável:

- tipo de dano;
- elemento;
- item ou subitem;
- G;
- U;
- T;
- pontuação;
- classificação;
- snapshot;
- comentário;
- recomendação;
- quantitativo quando exigido;
- fotos prontas;
- perfil válido.

---

# 29. Policies

Criar ou atualizar:

```text
ClassificationProfilePolicy
CivilDamageTypePolicy
CivilStructuralElementPolicy
CivilTemplatePolicy
DefectAssessmentPolicy
```

### Perfis

Somente administrador interno autorizado poderá:

- criar rascunho;
- editar rascunho;
- ativar;
- aposentar.

Um perfil usado não poderá ser alterado.

---

# 30. Form Requests

Criar:

```text
ClassificationProfiles/StoreClassificationProfileRequest
ClassificationProfiles/UpdateClassificationProfileRequest
ClassificationProfiles/ActivateClassificationProfileRequest
CivilAssessments/ApplyCivilClassificationRequest
CivilAssessments/SyncAssessmentQuantitiesRequest
CivilTemplates/StoreCommentTemplateRequest
CivilTemplates/StoreRecommendationTemplateRequest
```

---

# 31. Controllers e rotas

Controllers:

```text
ClassificationProfileController
CivilClassificationController
CivilDamageTypeController
CivilStructuralElementController
CivilTemplateController
DefectAssessmentQuantityController
```

Rotas principais:

```text
GET    /classification-profiles
POST   /classification-profiles
PATCH  /classification-profiles/{profile}
POST   /classification-profiles/{profile}/activate
POST   /classification-profiles/{profile}/retire

POST   /inspections/{inspection}/classification-profile

PUT    /defect-assessments/{assessment}/civil-classification
PUT    /defect-assessments/{assessment}/quantities
```

---

# 32. Páginas Vue

Criar:

```text
resources/js/pages/ClassificationProfiles/Index.vue
resources/js/pages/ClassificationProfiles/Create.vue
resources/js/pages/ClassificationProfiles/Edit.vue
resources/js/pages/ClassificationProfiles/Show.vue
```

Componentes:

```text
GutScoreSelector.vue
GutScorePreview.vue
CivilClassificationBadge.vue
CivilAssessmentForm.vue
CivilQuantityEditor.vue
CommentTemplateSelector.vue
RecommendationTemplateSelector.vue
ClassificationProfileSummary.vue
```

---

## 32.1 Formulário da avaliação

Ordem sugerida:

```text
1. Condição da avaria
2. Projeto de referência
3. Item / subitem
4. Elemento
5. Tipo de dano
6. Impacta atividade?
7. Quantitativos
8. Gravidade
9. Urgência
10. Tendência
11. Resultado GUT
12. Classificação CV
13. Prazo
14. Comentário
15. Recomendação
16. Fotos
```

---

## 32.2 Exibição do resultado

Exemplo:

```text
G: 3
U: 4
T: 3

Pontuação: 36
Classificação: CV-2
Prazo recomendado: 11/05/2028
```

---

# 33. Seed `CivilClassificationSeeder`

Criar:

```text
CivilClassificationSeeder
```

Deverá cadastrar:

- perfil em rascunho;
- procedimento;
- revisão;
- critérios 1 a 5;
- códigos CV-0 a CV-5;
- tipos de dano observados;
- elementos observados;
- templates de demonstração.

### Regra crítica

As faixas completas não deverão ser marcadas como oficiais sem o procedimento.

O seed de demonstração deverá usar:

```text
status = draft
notes = "Regras provisórias baseadas no relatório de referência."
```

---

# 34. Testes obrigatórios

## 34.1 GUT

Testar:

```text
3 × 4 × 3 = 36
3 × 5 × 2 = 30
3 × 4 × 1 = 12
```

Testar notas inválidas:

```text
0
6
```

---

## 34.2 Resolver CV

Com perfil de teste controlado:

- limite inferior;
- limite superior;
- cada faixa;
- lacuna;
- sobreposição;
- nenhuma faixa;
- duas faixas encontradas.

---

## 34.3 Perfil

Testar:

- cria rascunho;
- ativa perfil válido;
- bloqueia perfil incompleto;
- bloqueia sobreposição;
- bloqueia edição após uso;
- perfil de outro tenant;
- perfil de outro cliente;
- aposentadoria preserva inspeções antigas.

---

## 34.4 Avaliação

Testar:

- cálculo no backend;
- frontend não consegue enviar `gut_score` falso;
- frontend não consegue enviar classificação falsa;
- snapshot é salvo;
- prazo é calculado;
- reinspeção exige novo GUT;
- reparada limpa classificação atual;
- `not_inspected` não recebe CV;
- avaliação bloqueada após revisão.

---

## 34.5 Quantitativos

Testar:

- decimal preservado;
- unidade obrigatória;
- múltiplos quantitativos;
- soma por unidade;
- não soma unidades diferentes;
- isolamento por tenant.

---

## 34.6 Resumo

Usar exemplos confirmados:

```text
36 → CV-2
30 → CV-3
24 → CV-3
27 → CV-3
12 → CV-4
15 → CV-4
6  → CV-5
```

Testar:

- classificação mais crítica;
- quantidade por classe;
- total por unidade;
- prazo;
- avaria reparada fora do resumo ativo.

---

## 34.7 Templates

Testar:

- sugestão é copiada;
- alteração do template não muda texto antigo;
- usuário pode editar texto final;
- template de outro tenant é bloqueado.

---

# 35. Validação manual

1. Criar perfil em rascunho.
2. Cadastrar notas G, U e T.
3. Cadastrar faixas CV de teste.
4. Ativar o perfil.
5. Vincular à inspeção.
6. Abrir uma avaliação.
7. Informar `3, 4, 3`.
8. Confirmar pontuação `36`.
9. Confirmar classe configurada.
10. Adicionar quantitativo `1,20 m²`.
11. Selecionar tipo de dano.
12. Selecionar elemento.
13. Aplicar comentário sugerido.
14. Editar o comentário.
15. Aplicar recomendação.
16. Salvar.
17. Alterar o perfil.
18. Confirmar que a avaliação antiga não mudou.
19. Criar resumo.
20. Confirmar a classe mais crítica.

---

# 36. Critérios de aceite

- [ ] perfis versionados criados;
- [ ] critérios G, U e T configuráveis;
- [ ] faixas CV configuráveis;
- [ ] perfil congelado por inspeção;
- [ ] cálculo GUT no backend;
- [ ] classificação resolvida no backend;
- [ ] snapshot da regra salvo;
- [ ] prazo calculado;
- [ ] tipos de dano cadastrados;
- [ ] elementos cadastrados;
- [ ] quantitativos com unidades;
- [ ] comentários sugeridos;
- [ ] recomendações sugeridas;
- [ ] texto final editável;
- [ ] criticidade mais alta calculada;
- [ ] resumo por classe;
- [ ] reinspeção exige nova avaliação;
- [ ] reparo remove classificação ativa;
- [ ] validação antes da revisão;
- [ ] isolamento multiempresa;
- [ ] testes passam;
- [ ] build passa;
- [ ] regras oficiais não são inventadas;
- [ ] documentação corresponde ao código.

---

# 37. Riscos e brechas

## 37.1 Faixas inventadas

O relatório mostra resultados, mas não define todas as faixas.

Mitigação:

- perfil em rascunho;
- procedimento obrigatório;
- não ativar regra oficial sem validação.

---

## 37.2 Regra hard-coded

Alteração de revisão exigiria deploy e poderia mudar histórico.

Mitigação:

- perfil versionado;
- snapshot;
- resolver por dados.

---

## 37.3 Usuário envia classificação manual

O frontend poderia tentar forçar `CV-5`.

Mitigação:

- ignorar resultado enviado;
- calcular tudo no backend.

---

## 37.4 Alterar perfil usado

Isso modificaria a interpretação histórica.

Mitigação:

- perfil usado é imutável;
- criar nova versão.

---

## 37.5 Misturar unidades

Somar `m²`, `m³` e unidades produz total sem significado.

Mitigação:

- total por unidade;
- validação explícita.

---

## 37.6 Reparada continua crítica

Manter CV ativo após reparo falseia o resumo atual.

Mitigação:

- classificação atual nula;
- avaliação anterior preservada.

---

## 37.7 Template tratado como laudo automático

Texto sugerido pode não representar a situação real.

Mitigação:

- edição obrigatoriamente disponível;
- responsabilidade técnica humana;
- revisão.

---

# 38. Checklist de execução

- [ ] Obter procedimento oficial.
- [ ] Validar escala G.
- [ ] Validar escala U.
- [ ] Validar escala T.
- [ ] Validar faixas CV.
- [ ] Validar prazos.
- [ ] Criar enums.
- [ ] Criar migrations.
- [ ] Criar models.
- [ ] Criar profiles.
- [ ] Criar validador de perfil.
- [ ] Criar calculadora GUT.
- [ ] Criar resolvedor CV.
- [ ] Criar Action de classificação.
- [ ] Criar quantitativos.
- [ ] Criar templates.
- [ ] Criar resumo.
- [ ] Criar criticidade.
- [ ] Atualizar envio para revisão.
- [ ] Criar Policies.
- [ ] Criar Form Requests.
- [ ] Criar Controllers.
- [ ] Criar rotas.
- [ ] Criar páginas Vue.
- [ ] Criar seeder provisório.
- [ ] Criar testes.
- [ ] Executar migrations.
- [ ] Executar Pint.
- [ ] Executar testes.
- [ ] Executar build.
- [ ] Validar manualmente.
- [ ] Atualizar roadmap.
- [ ] Criar commit.

---

# 39. Commit sugerido

```bash
git add .
git commit -m "feat: add versioned civil GUT classification"
```

---

# 40. Próximo documento

```text
10-REVISAO-APROVACAO-E-AUDITORIA.md
```

O próximo documento definirá:

- apontamentos de revisão;
- correções por avaliação;
- aceite ou rejeição;
- aprovação;
- reabertura;
- revisões do relatório;
- trilha de auditoria;
- snapshots aprovados;
- bloqueios;
- testes.
