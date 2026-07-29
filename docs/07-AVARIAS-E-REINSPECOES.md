# 07 — Avarias e Reinspeções

## 1. Objetivo

Implementar a identidade permanente das avarias e o histórico de suas avaliações ao longo das inspeções.

A regra central é:

```text
A avaria permanece.
A avaliação muda em cada inspeção.
```

Exemplo:

```text
Avaria VT009-CV-004
├── Inspeção 2025 → CV-3
├── Inspeção 2026 → agravou para CV-2
└── Inspeção 2027 → reparada
```

O código `VT009-CV-004` continua o mesmo durante toda a existência dessa avaria.

---

## 2. Resultado esperado

Ao concluir esta etapa, o sistema deverá permitir:

- registrar uma nova avaria CIVIL;
- gerar automaticamente um código único;
- manter esse código em todas as reinspeções;
- criar uma avaliação diferente em cada inspeção;
- mostrar as avarias da inspeção anterior;
- exigir que o inspetor informe a situação de cada avaria anterior;
- marcar avaria como igual, agravada, melhorada ou reparada;
- registrar que a avaria não foi localizada;
- registrar impossibilidade de inspeção;
- criar novas avarias durante a reinspeção;
- relacionar uma nova avaria a uma anterior;
- tratar recorrência após reparo como nova avaria;
- impedir avaliações cruzadas entre equipamentos ou organizações;
- impedir duas avaliações da mesma avaria na mesma inspeção.

---

## 3. Escopo incluído

Será criado:

- campo de prefixo de avaria no equipamento;
- tabela de sequências de códigos;
- tabela `defects`;
- tabela `defect_assessments`;
- tabela `defect_relations`;
- enums de categoria, status, condição e relação;
- gerador transacional de código;
- criação de nova avaria com primeira avaliação;
- avaliação de avaria existente;
- fluxo da reinspeção;
- checklist de avarias anteriores;
- validação de cobertura antes da revisão;
- models e relacionamentos;
- Actions;
- Policies;
- Form Requests;
- Controllers;
- rotas;
- páginas Vue;
- factories;
- testes;
- seed de demonstração.

---

## 4. Fora do escopo

Não será implementado nesta etapa:

- cálculo GUT;
- classificação CV;
- tabelas de tipos de dano;
- quantitativos CIVIL;
- comentários padronizados;
- recomendações padronizadas;
- fotografias;
- localização em desenho técnico;
- relatório PDF;
- auditoria genérica;
- importação automática das avarias antigas.

Esses itens serão adicionados nos documentos seguintes.

---

# 5. Conceitos do domínio

## 5.1 Avaria

É a identidade permanente do problema.

Exemplo:

```text
VT009-CV-004
```

A avaria pertence permanentemente a:

```text
organização
equipamento
categoria
```

Ela não pertence a apenas uma inspeção.

---

## 5.2 Avaliação da avaria

Representa a condição da avaria em uma inspeção específica.

Exemplo:

```text
defect
- code: VT009-CV-004
- equipment: U03-06VT002

defect_assessment 2025
- condition: new

defect_assessment 2026
- condition: worsened

defect_assessment 2027
- condition: repaired
```

---

## 5.3 Relação entre avarias

Será usada quando uma nova avaria possui relação com outra.

Exemplos:

```text
split
recurrence
related
```

### `split`

Uma avaria ampla foi dividida em duas ou mais avarias específicas.

### `recurrence`

A avaria anterior foi reparada, mas um novo dano apareceu posteriormente no mesmo local.

A nova ocorrência recebe outro código.

### `related`

Existe relação técnica, mas não é divisão nem recorrência.

---

# 6. Regras de negócio

## 6.1 Código único

O código será único dentro da organização:

```text
organization_id + code
```

Duas organizações poderão possuir o mesmo código.

---

## 6.2 Código imutável

Após a criação:

```text
defects.code
```

não poderá ser alterado pela interface.

Uma correção excepcional exigirá procedimento administrativo e auditoria futura.

---

## 6.3 Geração automática

O inspetor não digitará livremente o código.

O sistema utilizará:

```text
prefixo do equipamento
+
código da categoria
+
sequência
```

Exemplo:

```text
VT009 + CV + 004
= VT009-CV-004
```

---

## 6.4 Prefixo do equipamento

Cada equipamento poderá possuir:

```text
defect_code_prefix
```

Exemplo:

```text
VT009
```

Quando não informado, o sistema poderá usar:

```text
normalized_tag
```

Porém, para manter códigos curtos e compatíveis com o processo atual, o administrador deverá preferencialmente configurar o prefixo.

---

## 6.5 Sequência

A sequência será controlada por:

```text
organização
equipamento
categoria
```

Exemplo:

```text
VT009-CV-001
VT009-CV-002
VT009-CV-003
```

A sequência nunca será reutilizada, mesmo se a avaria for reparada ou arquivada.

---

## 6.6 Categoria inicial

O MVP terá apenas:

```text
CIVIL
```

Código:

```text
CV
```

A modelagem permitirá adicionar futuramente:

```text
TAC
REC
```

sem trocar a identidade das avarias existentes.

---

## 6.7 Primeira avaliação

Uma nova avaria e sua primeira avaliação serão criadas na mesma transação.

Não poderá existir uma avaria nova sem primeira avaliação.

---

## 6.8 Uma avaliação por inspeção

A mesma avaria só poderá possuir uma avaliação por inspeção:

```text
defect_id + inspection_id
```

---

## 6.9 Mesmo equipamento

A avaliação só poderá relacionar:

```text
avaria do equipamento X
+
inspeção do equipamento X
```

Uma avaria do equipamento A não poderá ser avaliada na inspeção do equipamento B.

Essa proteção existirá no banco e na aplicação.

---

## 6.10 Situações da avaliação

Condições iniciais:

```text
new
unchanged
worsened
improved
repaired
not_located
not_inspected
```

### `new`

Avaria identificada pela primeira vez.

### `unchanged`

Permanece aproximadamente igual à avaliação anterior.

### `worsened`

A condição se agravou.

### `improved`

Houve melhora parcial, mas a avaria permanece ativa.

### `repaired`

O reparo foi confirmado.

### `not_located`

O inspetor procurou, mas não conseguiu localizar a avaria.

Isso não significa reparo.

### `not_inspected`

O local não pôde ser inspecionado.

Exige justificativa.

---

## 6.11 Status permanente da avaria

Estados:

```text
active
repaired
archived
```

### `active`

A avaria ainda exige acompanhamento.

### `repaired`

A avaliação atual confirmou o reparo.

### `archived`

Estado administrativo excepcional.

Não será usado para esconder uma avaria não resolvida.

---

## 6.12 Atualização do status

Condição da avaliação:

```text
new
unchanged
worsened
improved
not_located
not_inspected
```

mantém:

```text
defect.status = active
```

Condição:

```text
repaired
```

altera:

```text
defect.status = repaired
defect.repaired_at = data da avaliação
```

---

## 6.13 Recorrência

Se uma avaria reparada aparecer novamente:

- não reabrir silenciosamente a avaria anterior;
- criar uma nova avaria;
- gerar novo código;
- relacionar com a anterior usando `recurrence`.

Exemplo:

```text
VT009-CV-004 → reparada em 2027
VT009-CV-021 → recorrência em 2028
```

---

## 6.14 Divisão de avaria

Uma avaria anterior poderá originar várias novas avarias.

Exemplo:

```text
VT009-CV-004
├── VT009-CV-022
└── VT009-CV-023
```

As novas avarias recebem códigos próprios e relação:

```text
split
```

A avaria original deve receber uma avaliação na inspeção atual indicando sua situação final.

Não basta criar as filhas e ignorar a original.

---

## 6.15 Reinspeção não copia automaticamente

Ao criar uma reinspeção, o sistema não criará avaliações novas automaticamente.

Ele mostrará a lista anterior e aguardará a confirmação do inspetor.

Isso evita presumir que uma avaria ainda existe.

---

## 6.16 Cobertura obrigatória

Antes de enviar uma reinspeção para revisão, todas as avarias que exigem acompanhamento deverão possuir avaliação na inspeção atual.

Entram no checklist:

- avarias ativas avaliadas na inspeção anterior;
- avarias marcadas anteriormente como `not_located`;
- avarias marcadas anteriormente como `not_inspected`.

Não entram por padrão:

- avarias já reparadas;
- avarias arquivadas.

Uma avaria reparada pode ser consultada e usada para registrar uma recorrência.

---

## 6.17 Fotos atuais

As fotos serão tratadas no documento 08.

Nesta etapa fica definida a regra:

> avaliações `new`, `unchanged`, `worsened`, `improved` e `repaired` exigirão evidência fotográfica antes do envio para revisão.

Exceções:

```text
not_located
not_inspected
```

exigirão justificativa.

---

## 6.18 Edição permitida

Avaliações poderão ser criadas ou alteradas apenas quando a inspeção estiver:

```text
in_progress
in_correction
```

Não poderão ser alteradas livremente em:

```text
awaiting_review
awaiting_approval
approved
report_generated
released
canceled
```

---

## 6.19 Exclusão

Não haverá exclusão definitiva pela interface.

Uma avaliação criada incorretamente poderá ser corrigida enquanto estiver em rascunho.

Depois de enviada para revisão, correções deverão ocorrer pelo fluxo:

```text
in_correction
```

---

# 7. Enums

## 7.1 `DefectCategory.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectCategory: string
{
    case Civil = 'civil';

    public function code(): string
    {
        return match ($this) {
            self::Civil => 'CV',
        };
    }
}
```

---

## 7.2 `DefectStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectStatus: string
{
    case Active = 'active';
    case Repaired = 'repaired';
    case Archived = 'archived';
}
```

---

## 7.3 `DefectAssessmentCondition.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectAssessmentCondition: string
{
    case New = 'new';
    case Unchanged = 'unchanged';
    case Worsened = 'worsened';
    case Improved = 'improved';
    case Repaired = 'repaired';
    case NotLocated = 'not_located';
    case NotInspected = 'not_inspected';

    public function requiresReason(): bool
    {
        return in_array($this, [
            self::NotLocated,
            self::NotInspected,
        ], true);
    }

    public function keepsDefectActive(): bool
    {
        return $this !== self::Repaired;
    }
}
```

---

## 7.4 `DefectAssessmentStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectAssessmentStatus: string
{
    case Draft = 'draft';
    case Complete = 'complete';
}
```

---

## 7.5 `DefectRelationType.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectRelationType: string
{
    case Split = 'split';
    case Recurrence = 'recurrence';
    case Related = 'related';
}
```

---

# 8. Alterar equipamentos

Criar migration:

```bash
php artisan make:migration add_defect_code_prefix_to_equipments_table --table=equipments
```

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table): void {
            $table->string('defect_code_prefix', 80)
                ->nullable()
                ->after('normalized_tag');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table): void {
            $table->dropColumn('defect_code_prefix');
        });
    }
};
```

Adicionar ao `$fillable` de `Equipment`.

---

# 9. Ajustar índice das inspeções

Para garantir que uma avaliação use avaria e inspeção do mesmo equipamento, criar:

```bash
php artisan make:migration add_equipment_composite_key_to_inspections_table --table=inspections
```

```php
Schema::table('inspections', function (Blueprint $table): void {
    $table->unique(
        ['organization_id', 'equipment_id', 'id'],
        'inspections_org_equipment_id_unique',
    );
});
```

No `down()` remover o índice pelo nome.

---

# 10. Criar models e migrations

Comandos:

```bash
php artisan make:model DefectCodeSequence -mf
php artisan make:model Defect -mf
php artisan make:model DefectAssessment -mf
php artisan make:model DefectRelation -mf
```

Ordem:

```text
defect_code_sequences
defects
defect_assessments
defect_relations
```

---

# 11. Migration `defect_code_sequences`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_code_sequences', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');
            $table->string('category', 30);
            $table->unsignedInteger('last_number')->default(0);

            $table->timestamps();

            $table->foreign(
                ['organization_id', 'equipment_id'],
                'defect_sequences_org_equipment_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipments')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'equipment_id', 'category'],
                'defect_sequences_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_code_sequences');
    }
};
```

---

# 12. Migration `defects`

```php
<?php

declare(strict_types=1);

use App\Enums\DefectStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defects', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('first_inspection_id');

            $table->string('code', 150);
            $table->string('category', 30);
            $table->unsignedInteger('sequence_number');

            $table->string('title', 200);
            $table->text('origin_description')->nullable();

            $table->string('status', 30)
                ->default(DefectStatus::Active->value);

            $table->timestamp('repaired_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->foreign(
                ['organization_id', 'equipment_id'],
                'defects_org_equipment_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipments')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'equipment_id', 'first_inspection_id'],
                'defects_org_equipment_first_inspection_foreign',
            )
                ->references(['organization_id', 'equipment_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'code'],
                'defects_org_code_unique',
            );

            $table->unique(
                ['organization_id', 'equipment_id', 'id'],
                'defects_org_equipment_id_unique',
            );

            $table->unique(
                [
                    'organization_id',
                    'equipment_id',
                    'category',
                    'sequence_number',
                ],
                'defects_sequence_unique',
            );

            $table->index(
                ['organization_id', 'equipment_id', 'status'],
                'defects_org_equipment_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defects');
    }
};
```

---

# 13. Migration `defect_assessments`

```php
<?php

declare(strict_types=1);

use App\Enums\DefectAssessmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_assessments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('defect_id');
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('previous_assessment_id')->nullable();

            $table->string('condition', 30);
            $table->string('status', 20)
                ->default(DefectAssessmentStatus::Draft->value);

            $table->string('location_description', 500)->nullable();
            $table->text('comment')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('reason')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamp('assessed_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->foreign(
                ['organization_id', 'equipment_id', 'defect_id'],
                'assessments_org_equipment_defect_foreign',
            )
                ->references(['organization_id', 'equipment_id', 'id'])
                ->on('defects')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'equipment_id', 'inspection_id'],
                'assessments_org_equipment_inspection_foreign',
            )
                ->references(['organization_id', 'equipment_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->foreign('previous_assessment_id')
                ->references('id')
                ->on('defect_assessments')
                ->restrictOnDelete();

            $table->unique(
                ['defect_id', 'inspection_id'],
                'defect_assessments_defect_inspection_unique',
            );

            $table->unique(
                ['organization_id', 'equipment_id', 'id'],
                'assessments_org_equipment_id_unique',
            );

            $table->index(
                ['organization_id', 'inspection_id', 'status'],
                'assessments_org_inspection_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_assessments');
    }
};
```

---

# 14. Migration `defect_relations`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_relations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('source_defect_id');
            $table->unsignedBigInteger('target_defect_id');

            $table->string('relation_type', 30);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at');

            $table->foreign(
                ['organization_id', 'equipment_id', 'source_defect_id'],
                'defect_relations_source_foreign',
            )
                ->references(['organization_id', 'equipment_id', 'id'])
                ->on('defects')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'equipment_id', 'target_defect_id'],
                'defect_relations_target_foreign',
            )
                ->references(['organization_id', 'equipment_id', 'id'])
                ->on('defects')
                ->restrictOnDelete();

            $table->unique(
                ['source_defect_id', 'target_defect_id', 'relation_type'],
                'defect_relations_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_relations');
    }
};
```

---

# 15. Models

## 15.1 `Defect`

Relacionamentos:

```text
equipment
firstInspection
assessments
latestAssessment
outgoingRelations
incomingRelations
creator
```

Métodos:

```php
public function isActive(): bool
{
    return $this->status === DefectStatus::Active;
}

public function isRepaired(): bool
{
    return $this->status === DefectStatus::Repaired;
}

public function getRouteKeyName(): string
{
    return 'public_id';
}
```

Casts:

```text
category → DefectCategory
status → DefectStatus
repaired_at → datetime
archived_at → datetime
```

---

## 15.2 `DefectAssessment`

Relacionamentos:

```text
defect
inspection
previousAssessment
nextAssessments
creator
updater
```

Casts:

```text
condition → DefectAssessmentCondition
status → DefectAssessmentStatus
assessed_at → datetime
```

Métodos:

```php
public function isDraft(): bool
public function isComplete(): bool
public function requiresReason(): bool
```

---

## 15.3 `DefectCodeSequence`

Uso interno do gerador.

Não terá rota pública.

---

## 15.4 `DefectRelation`

Relacionamentos:

```text
sourceDefect
targetDefect
creator
```

Cast:

```text
relation_type → DefectRelationType
```

---

# 16. Atualizar relacionamentos

## 16.1 `Equipment`

Adicionar:

```php
public function defects(): HasMany
{
    return $this->hasMany(Defect::class);
}
```

---

## 16.2 `Inspection`

Adicionar:

```php
public function defectAssessments(): HasMany
{
    return $this->hasMany(DefectAssessment::class);
}
```

---

# 17. Serviço `DefectCodeGenerator`

Criar:

```text
app/Services/Defects/DefectCodeGenerator.php
```

Responsabilidades:

- validar tenant;
- bloquear contador;
- criar contador quando necessário;
- incrementar sequência;
- gerar código;
- não reutilizar número.

Estrutura:

```php
<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectCategory;
use App\Models\DefectCodeSequence;
use App\Models\Equipment;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

final class DefectCodeGenerator
{
    public function next(
        Equipment $equipment,
        DefectCategory $category,
    ): array {
        return DB::transaction(function () use (
            $equipment,
            $category,
        ): array {
            $sequence = DefectCodeSequence::query()
                ->where('organization_id', $equipment->organization_id)
                ->where('equipment_id', $equipment->id)
                ->where('category', $category->value)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = DefectCodeSequence::query()->create([
                    'organization_id' => $equipment->organization_id,
                    'equipment_id' => $equipment->id,
                    'category' => $category,
                    'last_number' => 0,
                ]);

                $sequence->refresh();
            }

            $nextNumber = $sequence->last_number + 1;

            $sequence->update([
                'last_number' => $nextNumber,
            ]);

            $prefix = TextNormalizer::technicalCode(
                $equipment->defect_code_prefix
                    ?: $equipment->normalized_tag,
            );

            return [
                'number' => $nextNumber,
                'code' => sprintf(
                    '%s-%s-%03d',
                    $prefix,
                    $category->code(),
                    $nextNumber,
                ),
            ];
        });
    }
}
```

---

## 17.1 Concorrência

Duas requisições simultâneas não podem receber o mesmo número.

Testar especificamente:

```text
requisição A
requisição B
```

Resultado esperado:

```text
...-001
...-002
```

---

# 18. Action `CreateDefectWithAssessment`

Criar:

```text
app/Actions/Defects/CreateDefectWithAssessment.php
```

Responsabilidades:

1. validar tenant;
2. validar inspeção;
3. validar estado editável;
4. validar equipamento;
5. gerar código;
6. criar avaria;
7. criar primeira avaliação com `condition = new`;
8. criar relação opcional;
9. concluir em transação.

Entrada sugerida:

```text
inspection
title
origin_description
location_description
comment
recommendation
related_defect_id
relation_type
```

---

## 18.1 Regra de relação

Quando houver `related_defect_id`:

- pertencer à mesma organização;
- pertencer ao mesmo equipamento;
- não ser a própria avaria;
- relação válida.

Para `recurrence`, a avaria anterior deve estar reparada.

---

# 19. Action `AssessExistingDefect`

Criar:

```text
app/Actions/Defects/AssessExistingDefect.php
```

Responsabilidades:

- validar tenant;
- validar mesmo equipamento;
- validar inspeção editável;
- impedir avaliação duplicada;
- localizar avaliação anterior;
- validar condição;
- exigir justificativa;
- criar avaliação;
- atualizar status permanente da avaria.

---

## 19.1 Avaliação anterior

Para reinspeção, usar preferencialmente a avaliação da inspeção anterior vinculada.

Caso não exista, usar a última avaliação cronológica anterior.

O `previous_assessment_id` deve ser gravado.

---

# 20. Action `UpdateDefectAssessment`

Permitida apenas em:

```text
in_progress
in_correction
```

Não poderá alterar:

```text
organization_id
equipment_id
defect_id
inspection_id
previous_assessment_id
```

Poderá alterar:

```text
condition
location_description
comment
recommendation
reason
internal_notes
```

Ao alterar de ou para `repaired`, recalcular o status permanente da avaria com cuidado.

---

# 21. Status derivado da última avaliação

Criar serviço:

```text
DefectStatusSynchronizer
```

Regra:

- buscar a última avaliação válida;
- `repaired` → avaria reparada;
- demais condições → avaria ativa;
- não usar simplesmente o último `updated_at`;
- usar ordem da inspeção/data e `assessed_at`.

Isso evita que uma edição de avaliação antiga altere o status atual incorretamente.

---

# 22. Query `BuildReinspectionChecklist`

Criar:

```text
app/Queries/Defects/BuildReinspectionChecklist.php
```

Entrada:

```text
current inspection
```

Saída:

```text
previous inspection
required defects
current assessment, se existente
coverage status
```

Exemplo:

```json
[
  {
    "defect_code": "VT009-CV-004",
    "previous_condition": "worsened",
    "previous_comment": "...",
    "current_assessment": null,
    "resolved": false
  }
]
```

---

# 23. Serviço `ReinspectionCoverageValidator`

Antes de:

```text
SubmitInspectionForReview
```

validar:

- inspeção inicial: não exige checklist anterior;
- reinspeção: todas as avarias obrigatórias avaliadas;
- avaliações obrigatórias completas;
- justificativas presentes;
- fotos serão validadas no documento 08;
- GUT será validado no documento 09.

Mensagem:

```text
Ainda existem 3 avarias anteriores sem avaliação.
```

---

# 24. Ajustar `SubmitInspectionForReview`

A Action criada no documento 06 deverá chamar:

```php
$coverageValidator->validate($inspection);
```

antes da transição.

Essa validação não ficará apenas no Vue.

---

# 25. Policies

## 25.1 `DefectPolicy`

Permissões:

```text
viewAny
view
create
archive
createRecurrence
createSplit
```

---

## 25.2 `DefectAssessmentPolicy`

Permissões:

```text
view
create
update
complete
```

Regras:

- mesmo tenant;
- inspeção editável;
- usuário ativo;
- usuário atribuído como inspetor ou preparador;
- administrador pode visualizar, mas não editar tecnicamente sem responsabilidade atribuída.

---

# 26. Form Requests

Criar:

```text
Defects/StoreDefectRequest
Defects/StoreExistingDefectAssessmentRequest
Defects/UpdateDefectAssessmentRequest
Defects/CreateRelatedDefectRequest
```

---

## 26.1 Nova avaria

Campos:

```text
title
origin_description
location_description
comment
recommendation
related_defect_id
relation_type
```

Categoria:

```text
civil
```

pode ser definida pelo backend no MVP.

---

## 26.2 Avaliação existente

Campos:

```text
condition
location_description
comment
recommendation
reason
internal_notes
```

Regras:

- `condition` usa enum;
- `reason` obrigatório para `not_located` e `not_inspected`;
- comentário será obrigatório quando a avaliação for concluída;
- recomendação poderá ficar pendente até o documento 09.

---

# 27. Controllers

Criar:

```bash
php artisan make:controller DefectController
php artisan make:controller DefectAssessmentController
php artisan make:controller ReinspectionChecklistController
```

---

## 27.1 `DefectController`

Métodos:

```text
index
show
store
storeRelated
```

Sem:

```text
destroy
```

---

## 27.2 `DefectAssessmentController`

Métodos:

```text
store
update
show
complete
```

---

## 27.3 `ReinspectionChecklistController`

Método:

```text
show
```

Retorna a página ou dados Inertia da reinspeção.

---

# 28. Rotas

```php
Route::get(
    'equipments/{equipment}/defects',
    [DefectController::class, 'index'],
)->name('equipments.defects.index');

Route::get(
    'defects/{defect}',
    [DefectController::class, 'show'],
)->name('defects.show');

Route::post(
    'inspections/{inspection}/defects',
    [DefectController::class, 'store'],
)->name('inspections.defects.store');

Route::post(
    'inspections/{inspection}/defects/{defect}/related',
    [DefectController::class, 'storeRelated'],
)->name('inspections.defects.related.store');

Route::post(
    'inspections/{inspection}/defects/{defect}/assessments',
    [DefectAssessmentController::class, 'store'],
)->name('defect-assessments.store');

Route::patch(
    'defect-assessments/{defectAssessment}',
    [DefectAssessmentController::class, 'update'],
)->name('defect-assessments.update');

Route::post(
    'defect-assessments/{defectAssessment}/complete',
    [DefectAssessmentController::class, 'complete'],
)->name('defect-assessments.complete');

Route::get(
    'inspections/{inspection}/reinspection-checklist',
    [ReinspectionChecklistController::class, 'show'],
)->name('inspections.reinspection-checklist');
```

---

# 29. Páginas Vue

Criar:

```text
resources/js/pages/Defects/Show.vue
resources/js/pages/Inspections/Defects/Index.vue
resources/js/pages/Inspections/Defects/Create.vue
resources/js/pages/Inspections/ReinspectionChecklist.vue
```

Componentes:

```text
DefectCodeBadge.vue
DefectStatusBadge.vue
DefectAssessmentForm.vue
DefectHistoryTimeline.vue
PreviousAssessmentCard.vue
ReinspectionChecklistItem.vue
CreateRelatedDefectModal.vue
```

---

## 29.1 Tela de reinspeção

Cada item deverá mostrar:

```text
Código
Título
Última condição
Última inspeção
Último comentário
Última recomendação
Fotos anteriores futuramente
Situação atual
```

Ações:

```text
Permanece igual
Agravou
Melhorou
Reparada
Não localizada
Não foi possível inspecionar
```

---

## 29.2 Progresso

Mostrar:

```text
12 de 15 avarias avaliadas
```

Não permitir envio para revisão enquanto faltar cobertura.

---

## 29.3 Histórico da avaria

Exemplo:

```text
VT009-CV-004

2025 — Nova
2026 — Agravou
2027 — Melhorou parcialmente
2028 — Reparada
```

Cada ponto deverá abrir a avaliação correspondente.

---

# 30. Factories

Criar:

```text
DefectCodeSequenceFactory
DefectFactory
DefectAssessmentFactory
DefectRelationFactory
```

States:

```text
active
repaired
new
unchanged
worsened
improved
notLocated
notInspected
complete
draft
```

Factories devem receber explicitamente:

```text
equipment
inspection
defect
```

para evitar combinações inválidas.

---

# 31. Testes obrigatórios

Criar:

```text
tests/Feature/Defects/
```

---

## 31.1 Código

Testar:

- primeiro código termina em `001`;
- segundo termina em `002`;
- sequência é separada por equipamento;
- sequência é separada por categoria;
- código é único por organização;
- mesmo código pode existir em outra organização;
- número não é reutilizado;
- código não pode ser alterado;
- concorrência não gera duplicidade.

---

## 31.2 Nova avaria

Testar:

- cria avaria e primeira avaliação;
- primeira condição é `new`;
- usa a mesma inspeção e equipamento;
- bloqueia inspeção de outra organização;
- bloqueia inspeção não editável;
- bloqueia equipamento divergente;
- rollback remove tudo se a avaliação falhar.

---

## 31.3 Avaliação existente

Testar:

- mesma avaria recebe avaliação em reinspeção;
- código permanece igual;
- não cria segunda avaliação na mesma inspeção;
- grava avaliação anterior;
- bloqueia avaria de outro equipamento;
- bloqueia avaria de outra organização;
- exige justificativa para `not_inspected`;
- exige justificativa para `not_located`.

---

## 31.4 Status

Testar:

- `new` mantém ativa;
- `unchanged` mantém ativa;
- `worsened` mantém ativa;
- `improved` mantém ativa;
- `not_located` mantém ativa;
- `not_inspected` mantém ativa;
- `repaired` altera para reparada;
- avaliação posterior ativa não deve reabrir silenciosamente uma avaria reparada;
- recorrência cria nova avaria.

---

## 31.5 Relações

Testar:

- cria relação `split`;
- cria relação `recurrence`;
- recorrência exige avaria anterior reparada;
- bloqueia relação com avaria de outro equipamento;
- bloqueia relação consigo mesma;
- bloqueia duplicidade.

---

## 31.6 Checklist

Testar:

- reinspeção lista avarias ativas anteriores;
- avaria reparada não entra por padrão;
- avaria `not_inspected` entra;
- avaria `not_located` entra;
- nova avaliação resolve o item;
- envio para revisão falha com pendências;
- envio funciona quando tudo está resolvido.

---

## 31.7 Snapshot histórico

Alterar título atual da avaria não deve mudar avaliações anteriores.

O relatório futuro deverá usar dados preservados na avaliação ou snapshot específico.

Se isso se mostrar necessário, o documento 10 deverá adicionar snapshot por revisão.

---

# 32. Seed de demonstração

Para o equipamento:

```text
U03-06VT002
```

configurar:

```text
defect_code_prefix = VT009
```

Criar exemplos:

```text
VT009-CV-001
VT009-CV-002
VT009-CV-003
```

Condições:

```text
new
worsened
repaired
```

O seed deve usar Actions ou dados coerentes com as mesmas regras.

---

# 33. Validação manual

1. Configurar prefixo `VT009`.
2. Criar inspeção.
3. Criar primeira avaria.
4. Confirmar código `VT009-CV-001`.
5. Criar segunda.
6. Confirmar `VT009-CV-002`.
7. Liberar inspeção de demonstração.
8. Criar reinspeção.
9. Abrir checklist.
10. Avaliar primeira como igual.
11. Avaliar segunda como agravada.
12. Marcar terceira como reparada.
13. Confirmar status permanente.
14. Criar recorrência da reparada.
15. Confirmar novo código.
16. Tentar enviar com item pendente.
17. Confirmar bloqueio.
18. Completar checklist.
19. Confirmar envio.

---

# 34. Comandos finais

```bash
php artisan migrate
vendor/bin/pint --dirty
php artisan test
npm run build
php artisan route:list
```

Se ainda for seguro reconstruir a base:

```bash
php artisan migrate:fresh --seed
```

---

# 35. Critérios de aceite

- [ ] prefixo de avaria configurável por equipamento;
- [ ] sequência transacional criada;
- [ ] tabela `defects` criada;
- [ ] tabela `defect_assessments` criada;
- [ ] tabela `defect_relations` criada;
- [ ] código gerado automaticamente;
- [ ] código único por organização;
- [ ] código permanece nas reinspeções;
- [ ] primeira avaliação criada junto da avaria;
- [ ] apenas uma avaliação por inspeção;
- [ ] integridade entre equipamento, inspeção e avaria;
- [ ] condições da reinspeção funcionam;
- [ ] status permanente é sincronizado;
- [ ] reparo não apaga histórico;
- [ ] recorrência gera nova avaria;
- [ ] divisão gera novas avarias relacionadas;
- [ ] checklist carrega pendências;
- [ ] envio para revisão bloqueia checklist incompleto;
- [ ] isolamento multiempresa funciona;
- [ ] testes passam;
- [ ] build passa;
- [ ] documentação corresponde ao código.

---

# 36. Riscos e brechas

## 36.1 Código baseado em prefixo editável

Alterar o prefixo do equipamento não pode alterar códigos antigos.

Mitigação:

- códigos ficam gravados;
- somente novos códigos usam o novo prefixo;
- alteração futura será auditada.

---

## 36.2 Reabrir avaria reparada

Reabrir o mesmo código após reparo confunde ocorrência antiga com nova.

Mitigação:

- nova avaria;
- novo código;
- relação `recurrence`.

---

## 36.3 Marcar `not_located` como reparada

Não localizar não comprova reparo.

Mitigação:

- mantém status ativo;
- exige justificativa;
- continua no checklist futuro.

---

## 36.4 Copiar avaliação automaticamente

Isso trataria informação antiga como atual.

Mitigação:

- checklist sem criação automática;
- confirmação humana obrigatória.

---

## 36.5 Status atual baseado em `updated_at`

Editar uma avaliação antiga poderia alterar o status permanente.

Mitigação:

- sincronizar pela ordem das inspeções e avaliação cronológica;
- bloquear edição antiga fora do fluxo.

---

## 36.6 Duas avaliações simultâneas

Duas requisições podem tentar avaliar a mesma avaria.

Mitigação:

- índice único;
- transação;
- tratamento amigável da violação.

---

## 36.7 Contador duplicado

Duas requisições podem criar o contador simultaneamente.

Mitigação:

- índice único do escopo;
- transação;
- retry controlado em erro de concorrência;
- teste específico.

---

## 36.8 Divisão sem encerramento da original

Criar avarias filhas e deixar a original indefinida quebra a cobertura.

Mitigação:

- exigir avaliação da original;
- checklist continua pendente até resolução.

---

# 37. Checklist de execução

- [ ] Criar enums.
- [ ] Adicionar prefixo ao equipamento.
- [ ] Adicionar índice composto à inspeção.
- [ ] Criar migrations.
- [ ] Criar models.
- [ ] Atualizar relacionamentos.
- [ ] Criar gerador de código.
- [ ] Criar Action de nova avaria.
- [ ] Criar Action de avaliação existente.
- [ ] Criar Action de atualização.
- [ ] Criar sincronizador de status.
- [ ] Criar relações entre avarias.
- [ ] Criar checklist de reinspeção.
- [ ] Criar validador de cobertura.
- [ ] Atualizar envio para revisão.
- [ ] Criar Policies.
- [ ] Criar Form Requests.
- [ ] Criar Controllers.
- [ ] Criar rotas.
- [ ] Criar páginas Vue.
- [ ] Criar factories.
- [ ] Criar testes de código.
- [ ] Criar testes de avaliação.
- [ ] Criar testes de status.
- [ ] Criar testes de relações.
- [ ] Criar testes de checklist.
- [ ] Atualizar seeder.
- [ ] Executar migrations.
- [ ] Executar Pint.
- [ ] Executar testes.
- [ ] Executar build.
- [ ] Validar manualmente.
- [ ] Atualizar roadmap.
- [ ] Criar commit.

---

# 38. Commit sugerido

```bash
git add .
git commit -m "feat: add persistent defects and reinspection workflow"
```

---

# 39. Próximo documento

```text
08-FOTOS-E-ARMAZENAMENTO.md
```

O próximo documento definirá:

- captura pelo celular;
- uploads temporários;
- validação;
- redimensionamento;
- compressão;
- miniaturas;
- processamento em fila;
- privacidade;
- ordenação;
- vínculo com avaliações;
- bloqueio de revisão com fotos pendentes;
- testes de arquivos.
