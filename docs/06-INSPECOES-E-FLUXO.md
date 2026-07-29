# 06 — Inspeções e Fluxo Operacional

## 1. Objetivo

Implementar o núcleo operacional das inspeções.

Esta etapa conecta:

```text
Organização
→ Cliente
→ Unidade
→ Área
→ Subárea
→ Equipamento
→ Inspeção
```

Cada equipamento poderá possuir várias inspeções ao longo dos anos.

Uma inspeção poderá ser:

- inspeção inicial;
- reinspeção vinculada a uma inspeção anterior.

A inspeção também terá:

- responsáveis;
- documentos de referência;
- snapshot dos dados cadastrais;
- máquina de estados;
- histórico de transições;
- datas de execução, revisão, aprovação e liberação.

---

## 2. Correção importante do fluxo anterior

Os documentos iniciais usaram o fluxo:

```text
Aguardando revisão
→ Aprovada
```

Esse fluxo não representa corretamente os casos em que revisão e aprovação são realizadas por pessoas diferentes.

O fluxo oficial passa a ser:

```text
Planejada
↓
Em inspeção
↓
Aguardando revisão
↓
Em correção
↓
Aguardando revisão
↓
Aguardando aprovação
↓
Aprovada
↓
Relatório gerado
↓
Liberada
```

Também será possível cancelar uma inspeção antes da liberação.

Os documentos `01-VISAO-GERAL-E-ESCOPO.md` e `02-ARQUITETURA-E-PADROES.md` deverão ser atualizados no mesmo commit para incluir o estado:

```text
awaiting_approval
```

---

## 3. Resultado esperado

Ao concluir esta etapa, o administrador interno deverá conseguir:

- criar uma inspeção para um equipamento;
- escolher inspeção inicial ou reinspeção;
- selecionar a inspeção anterior;
- atribuir responsáveis;
- selecionar documentos técnicos de referência;
- iniciar a inspeção;
- acompanhar o status;
- enviar para revisão;
- devolver para correção;
- concluir a revisão;
- enviar para aprovação;
- aprovar;
- marcar o relatório como gerado;
- liberar;
- cancelar com justificativa;
- consultar todo o histórico de transições.

O mesmo usuário poderá ocupar várias responsabilidades na mesma inspeção.

---

## 4. Escopo incluído

Será criado:

- tabela `inspections`;
- tabela `inspection_responsibles`;
- tabela `inspection_status_histories`;
- tabela `inspection_reference_documents`;
- enums de tipo, status e responsabilidade;
- geração de número da inspeção;
- snapshot imutável do contexto;
- models e relacionamentos;
- Actions de criação e transição;
- Policies;
- Form Requests;
- Controllers;
- rotas;
- páginas Vue;
- testes de fluxo;
- testes de isolamento;
- seed de demonstração.

---

## 5. Fora do escopo

Não será criado agora:

- cadastro das avarias;
- avaliações GUT;
- fotos de campo;
- comentários de correção por avaria;
- revisão detalhada por item;
- auditoria genérica completa;
- geração real do PDF;
- assinatura eletrônica;
- notificações por e-mail;
- importação de inspeções históricas;
- edição offline;
- agenda/calendário avançado.

As transições de relatório serão preparadas, mas a geração efetiva será implementada no documento `11-RELATORIO-PDF.md`.

---

## 6. Regras de negócio

### 6.1 Inspeção pertence a um equipamento

Toda inspeção deve possuir:

```text
organization_id
equipment_id
```

O cliente e a localização serão obtidos do equipamento no momento da criação.

---

### 6.2 Equipamento ativo

Uma nova inspeção só poderá ser criada quando:

```php
$equipment->canReceiveInspection() === true
```

Equipamentos inativos ou descomissionados continuam consultáveis, mas não recebem novas inspeções no fluxo comum.

---

### 6.3 Inspeção inicial

Uma inspeção inicial terá:

```text
inspection_type = initial
previous_inspection_id = null
```

---

### 6.4 Reinspeção

Uma reinspeção terá:

```text
inspection_type = reinspection
previous_inspection_id preenchido
```

A inspeção anterior deve:

- pertencer à mesma organização;
- pertencer ao mesmo equipamento;
- estar liberada;
- não ser a própria inspeção;
- ser anterior cronologicamente.

Apenas uma inspeção anterior direta será vinculada.

O histórico completo será obtido seguindo a cadeia:

```text
Inspeção 2024
└── Inspeção 2025
    └── Inspeção 2026
```

---

### 6.5 Inspeções paralelas

O MVP permitirá apenas uma inspeção operacional aberta por equipamento.

São consideradas abertas:

```text
planned
in_progress
awaiting_review
in_correction
awaiting_approval
approved
report_generated
```

Uma nova inspeção só poderá ser criada quando a anterior estiver:

```text
released
canceled
```

Isso evita duas equipes produzindo históricos concorrentes para o mesmo equipamento.

Essa regra poderá ser flexibilizada futuramente mediante tipo de inspeção.

---

### 6.6 Número da inspeção

Cada inspeção terá um número legível:

```text
INS-2026-000123
```

No MVP, ele poderá ser gerado usando o `id` interno depois da criação:

```text
INS-{ANO}-{ID COM 6 DÍGITOS}
```

Exemplo:

```text
id = 123
number = INS-2026-000123
```

A coluna terá unicidade por organização.

O formato poderá ser configurável futuramente.

---

### 6.7 Snapshot

A inspeção deve preservar os dados existentes no momento da criação.

O snapshot incluirá:

```json
{
  "organization": {
    "name": "Empresa de Inspeção"
  },
  "client": {
    "name": "Samarco Mineração S.A.",
    "document": null
  },
  "unit": {
    "name": "Ubu",
    "code": null
  },
  "area": {
    "name": "Usina III",
    "code": null
  },
  "subarea": {
    "name": "Forno de Endurecimento",
    "code": null
  },
  "equipment": {
    "tag": "U03-06VT002",
    "name": "Ventilador",
    "abc_code": "A",
    "installation_location": null
  }
}
```

O snapshot:

- será criado uma única vez;
- não será atualizado quando o cadastro mudar;
- será usado pelo relatório futuro;
- terá uma versão de schema.

---

### 6.8 Responsabilidades

Responsabilidades iniciais:

```text
inspector
preparer
reviewer
approver
releaser
```

#### Inspetor

Realiza o trabalho de campo.

#### Preparador

Organiza e prepara o conteúdo técnico do relatório.

#### Revisor

Verifica o conteúdo.

#### Aprovador

Aprova tecnicamente.

#### Liberador

Libera oficialmente o relatório.

---

### 6.9 Mesma pessoa em várias funções

Permitido:

```text
João
- inspector
- preparer
- reviewer
- approver
- releaser
```

Cada responsabilidade será um registro próprio.

---

### 6.10 Várias pessoas na mesma função

O sistema permitirá várias pessoas na mesma responsabilidade.

Exemplo:

```text
Inspectores
- João
- Maria
```

Um responsável poderá ser marcado como principal:

```text
is_primary = true
```

No MVP:

- pode haver vários inspetores;
- pode haver vários preparadores;
- deve haver apenas um principal por responsabilidade;
- o sistema não bloqueará colaboradores adicionais;
- o relatório futuro usará os principais.

A unicidade do responsável principal será garantida pela Action.

---

### 6.11 Responsáveis pertencem à organização

Um usuário atribuído deve:

- pertencer à mesma organização;
- estar ativo;
- não ser superadministrador;
- não estar suspenso.

---

### 6.12 Responsáveis mínimos

Para iniciar:

```text
pelo menos um inspector
```

Para enviar para revisão:

```text
pelo menos um preparer
pelo menos um reviewer
```

Para enviar para aprovação:

```text
pelo menos um approver
```

Para liberar:

```text
pelo menos um releaser
```

A mesma pessoa pode satisfazer mais de uma exigência.

---

### 6.13 Documentos de referência

A inspeção poderá selecionar documentos do equipamento.

A seleção preserva qual revisão foi usada.

Exemplo:

```text
Desenho U030600-S-551729 — Revisão 04
```

Se surgir uma revisão nova depois, a inspeção antiga continuará vinculada ao documento selecionado.

---

### 6.14 Estados

Estados oficiais:

```text
planned
in_progress
awaiting_review
in_correction
awaiting_approval
approved
report_generated
released
canceled
```

---

### 6.15 Transições permitidas

```text
planned → in_progress
planned → canceled

in_progress → awaiting_review
in_progress → canceled

awaiting_review → in_correction
awaiting_review → awaiting_approval
awaiting_review → canceled

in_correction → awaiting_review
in_correction → canceled

awaiting_approval → in_correction
awaiting_approval → approved
awaiting_approval → canceled

approved → report_generated

report_generated → released
```

Não serão permitidas transições genéricas.

---

### 6.16 Retorno da aprovação

Quando o aprovador rejeitar:

```text
awaiting_approval → in_correction
```

A justificativa será obrigatória.

Depois da correção:

```text
in_correction → awaiting_review
```

A revisão deve ocorrer novamente.

---

### 6.17 Cancelamento

O cancelamento exige:

- usuário autorizado;
- justificativa;
- data e hora;
- histórico;
- estado anterior.

Uma inspeção liberada não pode ser cancelada.

---

### 6.18 Alteração após aprovação

Uma inspeção aprovada não poderá voltar ao fluxo normal por simples mudança de status.

Uma reabertura excepcional exigirá uma Action específica, nova revisão e auditoria.

Essa funcionalidade será implementada no documento de revisão e auditoria.

---

### 6.19 Histórico de status

Toda transição deverá registrar:

- organização;
- inspeção;
- estado anterior;
- estado novo;
- usuário;
- justificativa;
- metadados;
- data e hora.

O histórico não poderá ser editado pela interface.

---

### 6.20 Datas resumidas

A tabela de inspeções terá campos resumidos:

```text
started_at
field_completed_at
reviewed_at
approved_at
report_generated_at
released_at
canceled_at
```

O histórico de status permanece a fonte detalhada.

---

## 7. Enums

### 7.1 `InspectionType.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionType: string
{
    case Initial = 'initial';
    case Reinspection = 'reinspection';
}
```

---

### 7.2 `InspectionStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case AwaitingReview = 'awaiting_review';
    case InCorrection = 'in_correction';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case ReportGenerated = 'report_generated';
    case Released = 'released';
    case Canceled = 'canceled';

    public function isOpen(): bool
    {
        return ! in_array($this, [
            self::Released,
            self::Canceled,
        ], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Released,
            self::Canceled,
        ], true);
    }
}
```

---

### 7.3 `InspectionResponsibility.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionResponsibility: string
{
    case Inspector = 'inspector';
    case Preparer = 'preparer';
    case Reviewer = 'reviewer';
    case Approver = 'approver';
    case Releaser = 'releaser';
}
```

---

## 8. Models e migrations

### 8.1 Comandos

```bash
php artisan make:model Inspection -mf
php artisan make:model InspectionResponsible -mf
php artisan make:model InspectionStatusHistory -mf
php artisan make:model InspectionReferenceDocument -mf
```

Ordem das migrations:

```text
inspections
inspection_responsibles
inspection_status_histories
inspection_reference_documents
```

---

# 9. Migration `inspections`

```php
<?php

declare(strict_types=1);

use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('previous_inspection_id')->nullable();

            $table->string('number', 40)->nullable();

            $table->string('inspection_type', 30)
                ->default(InspectionType::Initial->value);

            $table->string('status', 40)
                ->default(InspectionStatus::Planned->value);

            $table->string('service_order', 100)->nullable();
            $table->string('external_report_number', 150)->nullable();
            $table->string('procedure_number', 150)->nullable();
            $table->string('atmospheric_classification', 50)->nullable();

            $table->date('scheduled_for')->nullable();
            $table->date('inspected_on')->nullable();

            $table->json('context_snapshot');
            $table->unsignedSmallInteger('snapshot_version')->default(1);

            $table->text('general_notes')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('field_completed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('report_generated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['organization_id', 'id'],
                'inspections_org_id_unique',
            );

            $table->unique(
                ['organization_id', 'number'],
                'inspections_org_number_unique',
            );

            $table->foreign(
                ['organization_id', 'equipment_id'],
                'inspections_org_equipment_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipments')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'previous_inspection_id'],
                'inspections_org_previous_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'equipment_id', 'status'],
                'inspections_org_equipment_status_index',
            );

            $table->index(
                ['organization_id', 'scheduled_for'],
                'inspections_org_schedule_index',
            );

            $table->index(
                ['organization_id', 'inspected_on'],
                'inspections_org_inspected_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
```

---

## 9.1 Observação sobre número nulo

O número começa como `null`, pois o `id` ainda não existe.

A Action deverá:

1. criar a inspeção;
2. gerar o número usando o `id`;
3. atualizar dentro da mesma transação.

---

# 10. Migration `inspection_responsibles`

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
        Schema::create('inspection_responsibles', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('inspection_id');
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('responsibility', 30);
            $table->boolean('is_primary')->default(false);

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->foreign(
                ['organization_id', 'inspection_id'],
                'inspection_responsibles_org_inspection_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->unique(
                ['inspection_id', 'user_id', 'responsibility'],
                'inspection_responsibles_unique',
            );

            $table->index(
                ['organization_id', 'inspection_id', 'responsibility'],
                'inspection_responsibles_org_role_index',
            );

            $table->index(
                ['user_id', 'responsibility'],
                'inspection_responsibles_user_role_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_responsibles');
    }
};
```

---

# 11. Migration `inspection_status_histories`

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
        Schema::create('inspection_status_histories', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('inspection_id');

            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at');

            $table->foreign(
                ['organization_id', 'inspection_id'],
                'inspection_histories_org_inspection_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'inspection_id', 'created_at'],
                'inspection_histories_org_inspection_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_status_histories');
    }
};
```

---

# 12. Migration `inspection_reference_documents`

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
        Schema::create('inspection_reference_documents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('equipment_document_id');

            $table->foreignId('added_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at');

            $table->foreign(
                ['organization_id', 'inspection_id'],
                'inspection_ref_docs_org_inspection_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'equipment_document_id'],
                'inspection_ref_docs_org_document_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipment_documents')
                ->restrictOnDelete();

            $table->unique(
                ['inspection_id', 'equipment_document_id'],
                'inspection_ref_docs_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_reference_documents');
    }
};
```

---

## 12.1 Ajuste necessário em `equipment_documents`

Para a chave composta funcionar, adicionar na migration de documentos:

```php
$table->unique(
    ['organization_id', 'id'],
    'equipment_documents_org_id_unique',
);
```

Caso a migration já tenha sido executada, criar nova migration para adicionar o índice.

---

# 13. Model `Inspection`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Inspection extends Model
{
    /** @use HasFactory<\Database\Factories\InspectionFactory> */
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'equipment_id',
        'previous_inspection_id',
        'number',
        'inspection_type',
        'status',
        'service_order',
        'external_report_number',
        'procedure_number',
        'atmospheric_classification',
        'scheduled_for',
        'inspected_on',
        'context_snapshot',
        'snapshot_version',
        'general_notes',
        'started_at',
        'field_completed_at',
        'reviewed_at',
        'approved_at',
        'report_generated_at',
        'released_at',
        'canceled_at',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Inspection $inspection): void {
            $inspection->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'inspection_type' => InspectionType::class,
            'status' => InspectionStatus::class,
            'scheduled_for' => 'date',
            'inspected_on' => 'date',
            'context_snapshot' => 'array',
            'started_at' => 'datetime',
            'field_completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'report_generated_at' => 'datetime',
            'released_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function previousInspection(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'previous_inspection_id',
        );
    }

    public function nextInspections(): HasMany
    {
        return $this->hasMany(
            self::class,
            'previous_inspection_id',
        );
    }

    public function responsibles(): HasMany
    {
        return $this->hasMany(InspectionResponsible::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(InspectionStatusHistory::class);
    }

    public function referenceDocuments(): HasMany
    {
        return $this->hasMany(InspectionReferenceDocument::class);
    }

    public function inspectors(): HasMany
    {
        return $this->responsibles()
            ->where(
                'responsibility',
                InspectionResponsibility::Inspector->value,
            );
    }

    public function hasResponsibility(
        InspectionResponsibility $responsibility,
    ): bool {
        return $this->responsibles()
            ->where('responsibility', $responsibility->value)
            ->exists();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

# 14. Demais models

## 14.1 `InspectionResponsible`

Campos e casts:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionResponsibility;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionResponsible extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'inspection_id',
        'user_id',
        'responsibility',
        'is_primary',
        'assigned_by',
        'assigned_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'responsibility' => InspectionResponsibility::class,
            'is_primary' => 'boolean',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
```

---

## 14.2 `InspectionStatusHistory`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionStatusHistory extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'inspection_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => InspectionStatus::class,
            'to_status' => InspectionStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
```

---

## 14.3 `InspectionReferenceDocument`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionReferenceDocument extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'inspection_id',
        'equipment_document_id',
        'added_by',
        'created_at',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(
            EquipmentDocument::class,
            'equipment_document_id',
        );
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
```

---

# 15. Atualizar `Equipment`

Adicionar:

```php
public function inspections(): HasMany
{
    return $this->hasMany(Inspection::class);
}

public function releasedInspections(): HasMany
{
    return $this->inspections()
        ->where('status', InspectionStatus::Released->value);
}
```

---

# 16. Serviço `InspectionSnapshotBuilder`

Criar:

```text
app/Services/Inspections/InspectionSnapshotBuilder.php
```

```php
<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Models\Equipment;

final class InspectionSnapshotBuilder
{
    public const VERSION = 1;

    public function build(Equipment $equipment): array
    {
        $equipment->loadMissing([
            'organization',
            'client',
            'unit',
            'area',
            'subarea',
        ]);

        return [
            'organization' => [
                'name' => $equipment->organization->name,
                'legal_name' => $equipment->organization->legal_name,
                'document' => $equipment->organization->document,
            ],
            'client' => [
                'name' => $equipment->client->name,
                'legal_name' => $equipment->client->legal_name,
                'document' => $equipment->client->document,
            ],
            'unit' => [
                'name' => $equipment->unit->name,
                'code' => $equipment->unit->code,
            ],
            'area' => [
                'name' => $equipment->area->name,
                'code' => $equipment->area->code,
            ],
            'subarea' => $equipment->subarea === null
                ? null
                : [
                    'name' => $equipment->subarea->name,
                    'code' => $equipment->subarea->code,
                ],
            'equipment' => [
                'tag' => $equipment->tag,
                'name' => $equipment->name,
                'description' => $equipment->description,
                'manufacturer' => $equipment->manufacturer,
                'model' => $equipment->model,
                'serial_number' => $equipment->serial_number,
                'asset_code' => $equipment->asset_code,
                'abc_code' => $equipment->abc_code,
                'installation_location' => $equipment->installation_location,
            ],
        ];
    }
}
```

---

# 17. Serviço `InspectionTransitionGuard`

Criar:

```text
app/Services/Inspections/InspectionTransitionGuard.php
```

```php
<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Enums\InspectionStatus;

final class InspectionTransitionGuard
{
    private const ALLOWED = [
        'planned' => [
            'in_progress',
            'canceled',
        ],
        'in_progress' => [
            'awaiting_review',
            'canceled',
        ],
        'awaiting_review' => [
            'in_correction',
            'awaiting_approval',
            'canceled',
        ],
        'in_correction' => [
            'awaiting_review',
            'canceled',
        ],
        'awaiting_approval' => [
            'in_correction',
            'approved',
            'canceled',
        ],
        'approved' => [
            'report_generated',
        ],
        'report_generated' => [
            'released',
        ],
        'released' => [],
        'canceled' => [],
    ];

    public function allows(
        InspectionStatus $from,
        InspectionStatus $to,
    ): bool {
        return in_array(
            $to->value,
            self::ALLOWED[$from->value] ?? [],
            true,
        );
    }
}
```

---

# 18. Action `CreateInspection`

Criar:

```text
app/Actions/Inspections/CreateInspection.php
```

Responsabilidades:

1. validar equipamento;
2. impedir inspeção aberta concorrente;
3. validar tipo;
4. validar inspeção anterior;
5. criar snapshot;
6. criar inspeção;
7. gerar número;
8. registrar estado inicial;
9. vincular documentos;
10. atribuir responsáveis;
11. executar tudo em transação.

Estrutura principal:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Inspections\InspectionSnapshotBuilder;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateInspection
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly InspectionSnapshotBuilder $snapshotBuilder,
    ) {}

    public function handle(
        User $actor,
        Equipment $equipment,
        array $data,
    ): Inspection {
        return DB::transaction(function () use (
            $actor,
            $equipment,
            $data,
        ): Inspection {
            abort_unless(
                $equipment->belongsToOrganization($this->tenant->id()),
                404,
            );

            if (! $equipment->canReceiveInspection()) {
                throw ValidationException::withMessages([
                    'equipment' => 'O equipamento não pode receber nova inspeção.',
                ]);
            }

            $hasOpenInspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->where('equipment_id', $equipment->id)
                ->whereNotIn('status', [
                    InspectionStatus::Released->value,
                    InspectionStatus::Canceled->value,
                ])
                ->lockForUpdate()
                ->exists();

            if ($hasOpenInspection) {
                throw ValidationException::withMessages([
                    'equipment' => 'O equipamento já possui uma inspeção aberta.',
                ]);
            }

            $type = InspectionType::from($data['inspection_type']);

            $previousInspection = $this->resolvePreviousInspection(
                $equipment,
                $type,
                $data['previous_inspection_id'] ?? null,
            );

            $inspection = Inspection::query()->create([
                'organization_id' => $this->tenant->id(),
                'equipment_id' => $equipment->id,
                'previous_inspection_id' => $previousInspection?->id,
                'inspection_type' => $type,
                'status' => InspectionStatus::Planned,
                'service_order' => TextNormalizer::nullableText(
                    $data['service_order'] ?? null,
                ),
                'external_report_number' => TextNormalizer::nullableText(
                    $data['external_report_number'] ?? null,
                ),
                'procedure_number' => TextNormalizer::nullableText(
                    $data['procedure_number'] ?? null,
                ),
                'atmospheric_classification' => TextNormalizer::nullableText(
                    $data['atmospheric_classification'] ?? null,
                ),
                'scheduled_for' => $data['scheduled_for'] ?? null,
                'context_snapshot' => $this->snapshotBuilder->build($equipment),
                'snapshot_version' => InspectionSnapshotBuilder::VERSION,
                'general_notes' => TextNormalizer::nullableText(
                    $data['general_notes'] ?? null,
                ),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $inspection->update([
                'number' => sprintf(
                    'INS-%s-%06d',
                    now()->format('Y'),
                    $inspection->id,
                ),
            ]);

            InspectionStatusHistory::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $inspection->id,
                'from_status' => null,
                'to_status' => InspectionStatus::Planned,
                'changed_by' => $actor->id,
                'reason' => 'Inspeção criada.',
                'created_at' => now(),
            ]);

            return $inspection->refresh();
        });
    }

    private function resolvePreviousInspection(
        Equipment $equipment,
        InspectionType $type,
        mixed $previousInspectionId,
    ): ?Inspection {
        if ($type === InspectionType::Initial) {
            if ($previousInspectionId !== null) {
                throw ValidationException::withMessages([
                    'previous_inspection_id' => 'Inspeção inicial não pode possuir inspeção anterior.',
                ]);
            }

            return null;
        }

        if ($previousInspectionId === null) {
            throw ValidationException::withMessages([
                'previous_inspection_id' => 'Selecione a inspeção anterior.',
            ]);
        }

        $previous = Inspection::query()
            ->forOrganization($this->tenant->id())
            ->where('equipment_id', $equipment->id)
            ->where('status', InspectionStatus::Released->value)
            ->findOrFail($previousInspectionId);

        return $previous;
    }
}
```

---

## 18.1 Responsáveis e documentos

Para manter a Action pequena, atribuição de responsáveis e documentos poderá ser delegada a:

```text
AssignInspectionResponsible
AttachInspectionReferenceDocument
```

A Action de criação pode chamar essas Actions dentro da mesma transação.

---

# 19. Action `AssignInspectionResponsible`

Criar:

```text
app/Actions/Inspections/AssignInspectionResponsible.php
```

Regras:

- inspeção no tenant;
- usuário no tenant;
- usuário ativo;
- responsabilidade válida;
- não duplicar;
- ao definir principal, desmarcar principal anterior da mesma função;
- registrar quem atribuiu.

Usar transação e `lockForUpdate()`.

---

# 20. Action base para transições

Evitar uma rota pública genérica que aceite qualquer status.

Pode existir uma classe interna reutilizável:

```text
TransitionInspection
```

Ela deve ser chamada apenas por Actions específicas.

Responsabilidades:

- bloquear a inspeção com `lockForUpdate()`;
- validar tenant;
- validar estado atual;
- validar transição;
- atualizar status;
- atualizar timestamps;
- criar histórico;
- registrar ator e justificativa.

Actions públicas:

```text
StartInspection
SubmitInspectionForReview
ReturnInspectionForCorrection
CompleteInspectionReview
ApproveInspection
MarkInspectionReportGenerated
ReleaseInspection
CancelInspection
```

---

# 21. Requisitos das transições

## 21.1 `StartInspection`

```text
planned → in_progress
```

Exige:

- pelo menos um inspetor;
- ator com permissão;
- equipamento ainda operacional;
- `inspected_on` definido ou preenchido com a data atual;
- grava `started_at`.

---

## 21.2 `SubmitInspectionForReview`

```text
in_progress → awaiting_review
in_correction → awaiting_review
```

Exige:

- preparador;
- revisor;
- nenhum upload pendente futuramente;
- nenhuma avaria incompleta futuramente;
- grava `field_completed_at` na primeira submissão.

---

## 21.3 `ReturnInspectionForCorrection`

```text
awaiting_review → in_correction
awaiting_approval → in_correction
```

Exige:

- justificativa;
- revisor ou aprovador conforme etapa;
- não altera `field_completed_at`.

---

## 21.4 `CompleteInspectionReview`

```text
awaiting_review → awaiting_approval
```

Exige:

- revisor responsável;
- aprovador atribuído;
- grava `reviewed_at`.

---

## 21.5 `ApproveInspection`

```text
awaiting_approval → approved
```

Exige:

- aprovador responsável;
- grava `approved_at`;
- bloqueia edição técnica comum.

---

## 21.6 `MarkInspectionReportGenerated`

```text
approved → report_generated
```

Será chamada pelo sistema após a geração real do PDF.

Não deve ser exposta como botão manual comum.

Grava:

```text
report_generated_at
```

---

## 21.7 `ReleaseInspection`

```text
report_generated → released
```

Exige:

- liberador responsável;
- relatório existente futuramente;
- grava `released_at`.

---

## 21.8 `CancelInspection`

Permitido nos estados definidos.

Exige:

- administrador interno ou responsável autorizado;
- justificativa;
- grava `canceled_at`.

---

# 22. Policies

Criar:

```text
InspectionPolicy
```

Permissões:

```text
viewAny
view
create
updatePlanned
assignResponsibles
start
submitForReview
returnForCorrection
completeReview
approve
release
cancel
```

---

## 22.1 Administrador interno

Pode:

- criar;
- editar enquanto planejada;
- atribuir responsáveis;
- cancelar;
- visualizar tudo da organização.

Não deve aprovar automaticamente sem estar atribuído como aprovador, salvo regra futura explícita.

---

## 22.2 Membro

Pode atuar somente quando:

- pertence à organização;
- está ativo;
- está atribuído à responsabilidade adequada;
- a inspeção está no estado correto.

---

## 22.3 Superadministrador

Não opera dentro de inspeções sem contexto explícito.

---

# 23. Form Requests

Criar:

```text
Inspections/StoreInspectionRequest
Inspections/UpdatePlannedInspectionRequest
Inspections/AssignResponsibleRequest
Inspections/SubmitForReviewRequest
Inspections/ReturnForCorrectionRequest
Inspections/CompleteReviewRequest
Inspections/ApproveInspectionRequest
Inspections/ReleaseInspectionRequest
Inspections/CancelInspectionRequest
```

---

## 23.1 `StoreInspectionRequest`

Campos:

```text
equipment_id
inspection_type
previous_inspection_id
service_order
external_report_number
procedure_number
atmospheric_classification
scheduled_for
general_notes
responsibles
reference_document_ids
```

A Action continua responsável por validar toda a cadeia.

---

## 23.2 Justificativas

Obrigatórias em:

```text
return for correction
cancel
```

Limite inicial:

```text
10 a 5000 caracteres
```

---

# 24. Controllers

Criar:

```bash
php artisan make:controller InspectionController
php artisan make:controller InspectionResponsibleController
php artisan make:controller InspectionTransitionController
```

---

## 24.1 `InspectionController`

Métodos:

```text
index
create
store
show
edit
update
```

Não criar:

```text
destroy
```

---

## 24.2 `InspectionResponsibleController`

Métodos:

```text
store
update
destroy
```

A remoção só será permitida enquanto a inspeção não estiver liberada e sem quebrar requisitos do estado atual.

---

## 24.3 `InspectionTransitionController`

Métodos explícitos:

```text
start
submitForReview
returnForCorrection
completeReview
approve
release
cancel
```

Não criar:

```text
updateStatus
```

---

# 25. Rotas

```php
Route::resource('inspections', InspectionController::class)
    ->except(['destroy']);

Route::post(
    'inspections/{inspection}/responsibles',
    [InspectionResponsibleController::class, 'store'],
)->name('inspections.responsibles.store');

Route::delete(
    'inspections/{inspection}/responsibles/{responsible}',
    [InspectionResponsibleController::class, 'destroy'],
)->name('inspections.responsibles.destroy');

Route::post(
    'inspections/{inspection}/start',
    [InspectionTransitionController::class, 'start'],
)->name('inspections.start');

Route::post(
    'inspections/{inspection}/submit-for-review',
    [InspectionTransitionController::class, 'submitForReview'],
)->name('inspections.submit-for-review');

Route::post(
    'inspections/{inspection}/return-for-correction',
    [InspectionTransitionController::class, 'returnForCorrection'],
)->name('inspections.return-for-correction');

Route::post(
    'inspections/{inspection}/complete-review',
    [InspectionTransitionController::class, 'completeReview'],
)->name('inspections.complete-review');

Route::post(
    'inspections/{inspection}/approve',
    [InspectionTransitionController::class, 'approve'],
)->name('inspections.approve');

Route::post(
    'inspections/{inspection}/release',
    [InspectionTransitionController::class, 'release'],
)->name('inspections.release');

Route::post(
    'inspections/{inspection}/cancel',
    [InspectionTransitionController::class, 'cancel'],
)->name('inspections.cancel');
```

A rota para `report_generated` será interna ou acionada pelo Job de PDF.

---

# 26. Páginas Vue

Criar:

```text
resources/js/pages/Inspections/Index.vue
resources/js/pages/Inspections/Create.vue
resources/js/pages/Inspections/Edit.vue
resources/js/pages/Inspections/Show.vue
```

Componentes:

```text
InspectionStatusBadge.vue
InspectionTimeline.vue
InspectionResponsibleList.vue
InspectionResponsibleForm.vue
InspectionReferenceDocuments.vue
InspectionTransitionActions.vue
InspectionSnapshotCard.vue
ReinspectionHistory.vue
```

---

## 26.1 Tela de listagem

Filtros:

```text
search
client
unit
equipment
status
inspection_type
scheduled_from
scheduled_to
inspected_from
inspected_to
responsible
```

Mostrar:

- número;
- equipamento;
- TAG;
- cliente;
- tipo;
- data;
- status;
- responsável principal;
- ações.

---

## 26.2 Tela de criação

Passos sugeridos:

```text
1. Selecionar equipamento
2. Definir tipo
3. Selecionar inspeção anterior, se reinspeção
4. Informar dados técnicos
5. Selecionar documentos
6. Atribuir responsáveis
7. Confirmar
```

O formulário pode ser uma única página dividida em seções no MVP.

---

## 26.3 Tela de detalhes

Abas sugeridas:

```text
Resumo
Equipe
Documentos
Avarias
Histórico
Relatório
```

Nesta etapa:

- Resumo;
- Equipe;
- Documentos;
- Histórico.

As demais serão implementadas depois.

---

## 26.4 Timeline

Exemplo:

```text
29/07/2026 09:00 — Planejada — Carlos
29/07/2026 10:15 — Em inspeção — João
29/07/2026 16:40 — Aguardando revisão — João
30/07/2026 08:20 — Em correção — Maria
```

---

# 27. Factories

Criar factories para:

```text
Inspection
InspectionResponsible
InspectionStatusHistory
InspectionReferenceDocument
```

States recomendados:

```text
initial
reinspection
inProgress
awaitingReview
inCorrection
awaitingApproval
approved
reportGenerated
released
canceled
```

A factory de reinspeção deve receber explicitamente:

```text
equipment
previousInspection
```

para evitar hierarquia inválida.

---

# 28. Testes obrigatórios

Criar:

```text
tests/Feature/Inspections/
```

---

## 28.1 Criação

Testar:

- administrador cria inspeção;
- membro sem permissão não cria;
- organização vem do tenant;
- snapshot é criado;
- número é gerado;
- inspeção inicial não aceita anterior;
- reinspeção exige anterior;
- anterior pertence ao mesmo equipamento;
- anterior deve estar liberada;
- não cria para equipamento inativo;
- não cria quando já existe inspeção aberta;
- isolamento entre organizações.

---

## 28.2 Snapshot

Testar:

1. criar equipamento;
2. criar inspeção;
3. alterar TAG e localização do equipamento;
4. confirmar que o snapshot da inspeção não mudou.

---

## 28.3 Responsáveis

Testar:

- atribui usuário ativo;
- bloqueia usuário de outra organização;
- bloqueia usuário inativo;
- mesma pessoa ocupa várias funções;
- não duplica mesma pessoa na mesma função;
- permite vários inspetores;
- mantém apenas um principal por função;
- membro não atribui sem permissão.

---

## 28.4 Fluxo

Testar todas as transições permitidas.

Testar transições proibidas:

```text
planned → approved
in_progress → released
awaiting_review → report_generated
released → in_progress
canceled → planned
```

---

## 28.5 Requisitos por transição

Testar:

- não inicia sem inspetor;
- não envia para revisão sem preparador;
- não envia para revisão sem revisor;
- não conclui revisão sem aprovador;
- não aprova sem aprovador atribuído;
- não libera sem liberador;
- retorno exige justificativa;
- cancelamento exige justificativa.

---

## 28.6 Concorrência

Testar, quando viável:

- duas criações concorrentes para o mesmo equipamento;
- duas aprovações simultâneas;
- dois responsáveis principais na mesma função.

As Actions devem usar transação e bloqueio.

---

## 28.7 Referências

Testar:

- adiciona documento do mesmo equipamento;
- bloqueia documento de outro equipamento;
- bloqueia documento de outra organização;
- revisão selecionada permanece vinculada;
- documento inativado continua visível no histórico.

---

# 29. Seed de demonstração

Criar para o equipamento `U03-06VT002`:

```text
Inspeção
- tipo: reinspection ou initial conforme base disponível
- O.S.: 3500762191
- data: 11/05/2026
- procedimento: T000000-S-2PO006_R-04
- classificação atmosférica: C4
- status inicial: planned
```

Atribuir usuários de demonstração:

```text
Inspector
Preparer
Reviewer
Approver
Releaser
```

Podem ser a mesma pessoa no seed simples.

---

# 30. Validação manual

1. Criar inspeção inicial.
2. Atribuir inspetor.
3. Tentar iniciar sem inspetor em outra inspeção.
4. Confirmar bloqueio.
5. Iniciar.
6. Atribuir preparador e revisor.
7. Enviar para revisão.
8. Devolver para correção.
9. Reenviar.
10. Concluir revisão.
11. Aprovar.
12. Confirmar bloqueio de edição comum.
13. Simular relatório gerado.
14. Liberar.
15. Abrir o histórico.
16. Criar reinspeção baseada na liberada.
17. Alterar o cadastro do equipamento.
18. Confirmar snapshot antigo.

---

# 31. Comandos finais

```bash
php artisan migrate
vendor/bin/pint --dirty
php artisan test
npm run build
php artisan route:list
```

Caso ainda seja seguro reconstruir o banco:

```bash
php artisan migrate:fresh --seed
```

---

# 32. Critérios de aceite

- [ ] tabela `inspections` criada;
- [ ] tabela de responsáveis criada;
- [ ] tabela de histórico criada;
- [ ] tabela de referências criada;
- [ ] número da inspeção é gerado;
- [ ] snapshot é preservado;
- [ ] inspeção inicial funciona;
- [ ] reinspeção funciona;
- [ ] apenas uma inspeção aberta por equipamento;
- [ ] mesma pessoa pode ocupar várias funções;
- [ ] vários inspetores são permitidos;
- [ ] principal por função é controlado;
- [ ] máquina de estados funciona;
- [ ] transições inválidas são bloqueadas;
- [ ] justificativas obrigatórias são validadas;
- [ ] histórico registra todas as transições;
- [ ] documentos de referência são preservados;
- [ ] isolamento multiempresa funciona;
- [ ] testes passam;
- [ ] build passa;
- [ ] documentos anteriores foram atualizados com `awaiting_approval`;
- [ ] documentação corresponde ao código.

---

# 33. Riscos e brechas

## 33.1 Fluxo sem estado de aprovação

Misturar revisão e aprovação elimina uma etapa real do relatório.

Mitigação:

- estado `awaiting_approval`;
- responsabilidades distintas;
- mesma pessoa ainda pode executar ambas.

---

## 33.2 Duas inspeções abertas

Duas equipes poderiam criar históricos conflitantes.

Mitigação:

- bloqueio por equipamento;
- transação;
- `lockForUpdate()`;
- teste de concorrência.

---

## 33.3 Snapshot atualizado silenciosamente

Atualizar snapshot ao editar equipamento destruiria o histórico.

Mitigação:

- snapshot imutável;
- sem campo em formulário;
- teste específico.

---

## 33.4 Usuário responsável desativado

Uma pessoa pode sair da empresa durante a inspeção.

Mitigação:

- preservar atribuição histórica;
- impedir novas ações do inativo;
- administrador substitui responsável;
- registrar mudança.

---

## 33.5 Aprovação pelo administrador não atribuído

Ser administrador não significa ser responsável técnico.

Mitigação:

- exigir atribuição como aprovador;
- separar permissão administrativa de responsabilidade técnica.

---

## 33.6 Várias pessoas principais

O MySQL não possui índice parcial simples para `is_primary = true`.

Mitigação:

- Action transacional;
- `lockForUpdate()`;
- desmarcar principal anterior;
- testes.

---

## 33.7 Relatório marcado manualmente como gerado

Isso permitiria liberar uma inspeção sem PDF válido.

Mitigação:

- transição interna;
- Job de relatório como responsável;
- validar arquivo no documento 11.

---

## 33.8 Cancelamento sem rastreabilidade

Apagar inspeção planejada removeria o histórico de tentativas.

Mitigação:

- não excluir;
- usar `canceled`;
- justificativa obrigatória.

---

# 34. Checklist de execução

- [ ] Atualizar documentos 01 e 02.
- [ ] Criar enums.
- [ ] Criar migrations.
- [ ] Adicionar índice composto aos documentos.
- [ ] Criar models.
- [ ] Atualizar `Equipment`.
- [ ] Criar snapshot builder.
- [ ] Criar transition guard.
- [ ] Criar Action de inspeção.
- [ ] Criar Action de responsáveis.
- [ ] Criar Actions de transição.
- [ ] Criar Policies.
- [ ] Criar Form Requests.
- [ ] Criar Controllers.
- [ ] Criar rotas.
- [ ] Criar páginas Vue.
- [ ] Criar factories.
- [ ] Criar testes de criação.
- [ ] Criar testes de snapshot.
- [ ] Criar testes de responsáveis.
- [ ] Criar testes de fluxo.
- [ ] Criar testes de referências.
- [ ] Atualizar seeder.
- [ ] Executar migrations.
- [ ] Executar Pint.
- [ ] Executar testes.
- [ ] Executar build.
- [ ] Validar manualmente.
- [ ] Atualizar roadmap.
- [ ] Criar commit.

---

# 35. Commit sugerido

```bash
git add .
git commit -m "feat: implement inspection lifecycle and responsibilities"
```

---

# 36. Próximo documento

```text
07-AVARIAS-E-REINSPECOES.md
```

O próximo documento definirá:

- identidade permanente da avaria;
- código único por organização;
- avaliação por inspeção;
- vínculo histórico;
- situações da reinspeção;
- nova avaria;
- avaria reparada;
- avaria agravada;
- divisão e recorrência;
- testes.
