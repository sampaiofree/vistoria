# 05 — Equipamentos e Documentos

## 1. Objetivo

Implementar o cadastro permanente dos equipamentos e seus documentos técnicos.

O equipamento será o ponto central do histórico do sistema.

Cada equipamento poderá possuir:

- um cliente;
- uma unidade;
- uma área;
- uma subárea opcional;
- um TAG;
- um nome;
- códigos auxiliares;
- informações técnicas;
- desenhos de referência;
- documentos;
- várias inspeções ao longo do tempo.

A estrutura principal será:

```text
Organização
└── Cliente
    └── Unidade
        └── Área
            └── Subárea
                └── Equipamento
                    ├── Documentos
                    └── Inspeções futuras
```

---

## 2. Resultado esperado

Ao concluir esta etapa, o administrador interno deverá conseguir:

- cadastrar equipamentos;
- editar dados cadastrais;
- ativar e inativar equipamentos;
- localizar equipamentos por TAG;
- filtrar por cliente, unidade, área e subárea;
- anexar documentos técnicos;
- classificar documentos por tipo;
- versionar documentos;
- definir um documento como atual;
- consultar documentos antigos;
- impedir TAG duplicado dentro da mesma unidade;
- manter o histórico mesmo após mudanças cadastrais.

Membros comuns poderão consultar equipamentos e documentos, mas não alterar cadastros.

---

## 3. Escopo incluído

Será criado:

- tabela `equipments`;
- tabela `equipment_documents`;
- enum de status do equipamento;
- enum de tipo de documento;
- enum de status do documento;
- normalização de TAG;
- models e relacionamentos;
- factories;
- actions;
- form requests;
- policies;
- controllers Inertia;
- rotas;
- páginas Vue;
- upload privado de documentos;
- versionamento de documentos;
- testes;
- seed de demonstração.

---

## 4. Fora do escopo

Não será criado agora:

- inspeções;
- avarias;
- avaliações;
- fotos de campo;
- relatório PDF;
- OCR;
- leitura automática de desenhos;
- editor visual de desenhos;
- assinatura eletrônica;
- comparação automática entre documentos;
- importação em massa;
- histórico completo de alterações cadastrais;
- documentos específicos por inspeção;
- vínculo de documento com avaria.

---

## 5. Regras de negócio

### 5.1 Equipamento pertence a uma organização

```text
equipments.organization_id
```

O `organization_id` será definido pelo backend usando o `TenantContext`.

Nunca será aceito livremente do frontend.

---

### 5.2 Equipamento pertence a uma unidade

Todo equipamento deve possuir:

```text
client_id
client_unit_id
area_id
```

A subárea poderá ser opcional:

```text
subarea_id = null
```

Isso permite cadastrar equipamentos que ainda não possuem uma subdivisão detalhada.

---

### 5.3 TAG único por unidade

A regra aprovada é:

```text
organization_id
+ client_id
+ client_unit_id
+ normalized_tag
```

Exemplo permitido:

```text
Cliente A / Unidade Norte / BOMBA-001
Cliente A / Unidade Sul   / BOMBA-001
Cliente B / Unidade Norte / BOMBA-001
```

Exemplo bloqueado:

```text
Cliente A / Unidade Norte / BOMBA-001
Cliente A / Unidade Norte / bomba-001
```

Os dois últimos representam o mesmo TAG normalizado.

---

### 5.4 TAG não é chave primária

O equipamento terá:

```text
id
public_id
tag
normalized_tag
```

O TAG poderá ser corrigido sem perder relacionamentos históricos.

---

### 5.5 Normalização do TAG

A normalização deverá:

- remover espaços externos;
- converter para maiúsculas;
- remover espaços internos desnecessários;
- preservar hífen, barra, ponto e outros caracteres técnicos permitidos.

Exemplos:

```text
" u03-06vt002 "  → "U03-06VT002"
" bomba 001 "    → "BOMBA001"
"EQ / 12"        → "EQ/12"
```

---

### 5.6 Integridade hierárquica

O sistema deve impedir:

- equipamento em cliente de outra organização;
- equipamento em unidade de outro cliente;
- equipamento em área de outra unidade;
- equipamento em subárea de outra área;
- equipamento associado a estrutura inativa em novo cadastro.

As relações deverão ser protegidas por:

- Form Requests;
- Actions;
- Policies;
- chaves estrangeiras;
- testes.

---

### 5.7 Estrutura operacional ativa

Para criar um equipamento, devem estar ativos:

- cliente;
- unidade;
- área;
- subárea, quando informada.

Depois de criado, um equipamento permanece consultável mesmo se algum pai for inativado.

---

### 5.8 Status do equipamento

Estados iniciais:

```text
active
inactive
decommissioned
```

#### `active`

Pode receber novas inspeções.

#### `inactive`

Permanece no histórico, mas não recebe nova inspeção normalmente.

#### `decommissioned`

Equipamento retirado definitivamente de operação.

Não deve ser reutilizado com outro significado.

---

### 5.9 Desativação não apaga histórico

Inativar ou descomissionar um equipamento:

- não apaga inspeções;
- não apaga documentos;
- não libera o TAG para outro equipamento;
- não remove o equipamento de relatórios antigos.

---

### 5.10 Mudança de localização

Alterar área ou subárea de um equipamento pode afetar o significado histórico.

No MVP:

- a edição cadastral poderá alterar a localização atual;
- inspeções futuras usarão a nova localização;
- inspeções antigas preservarão snapshots próprios;
- a mudança deverá ser auditada posteriormente.

Não haverá transferência automática em massa.

---

### 5.11 Documentos do equipamento

Um equipamento poderá possuir vários documentos.

Exemplos:

- desenho geral;
- desenho de montagem;
- memorial;
- manual;
- ficha técnica;
- procedimento;
- relatório anterior;
- outro.

---

### 5.12 Versionamento de documentos

Documentos técnicos deverão possuir:

```text
document_group
revision
is_current
```

Exemplo:

```text
Desenho U030600-S-551729
├── Revisão 02
├── Revisão 03
└── Revisão 04 — atual
```

A revisão antiga permanece armazenada.

---

### 5.13 Documento atual

Dentro do mesmo grupo documental, apenas um registro poderá estar como:

```text
is_current = true
```

Ao marcar uma nova revisão como atual, a anterior deverá ser desmarcada dentro de uma transação.

---

### 5.14 Arquivos privados

Documentos não ficarão expostos diretamente em `public/`.

O acesso será feito por rota autorizada ou URL temporária.

---

### 5.15 Exclusão de documentos

Um documento usado por inspeção futura não deverá ser apagado.

No MVP:

- documentos poderão ser inativados;
- exclusão física não será exposta na interface;
- arquivo órfão será tratado por rotina administrativa futura.

---

## 6. Modelo de dados

### 6.1 `equipments`

```text
id
public_id
organization_id
client_id
client_unit_id
area_id
subarea_id
tag
normalized_tag
name
description
manufacturer
model
serial_number
asset_code
abc_code
installation_location
commissioned_at
status
notes
created_by
updated_by
created_at
updated_at
deleted_at
```

---

### 6.2 `equipment_documents`

```text
id
public_id
organization_id
equipment_id
document_group
document_type
title
document_number
revision
description
disk
path
original_name
mime_type
extension
size
checksum
is_current
status
uploaded_by
issued_at
created_at
updated_at
deleted_at
```

---

## 7. Enums

### 7.1 `EquipmentStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum EquipmentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Decommissioned = 'decommissioned';
}
```

---

### 7.2 `EquipmentDocumentType.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum EquipmentDocumentType: string
{
    case GeneralDrawing = 'general_drawing';
    case AssemblyDrawing = 'assembly_drawing';
    case TechnicalDrawing = 'technical_drawing';
    case Manual = 'manual';
    case DataSheet = 'data_sheet';
    case Procedure = 'procedure';
    case PreviousReport = 'previous_report';
    case Memorial = 'memorial';
    case Other = 'other';
}
```

---

### 7.3 `DocumentStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
```

---

## 8. Criar models e migrations

### 8.1 Comandos

```bash
php artisan make:model Equipment -mf
php artisan make:model EquipmentDocument -mf
```

Garantir que a migration de `equipments` seja executada antes de `equipment_documents`.

---

# 9. Migration `equipments`

```php
<?php

declare(strict_types=1);

use App\Enums\EquipmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('client_unit_id');
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('subarea_id')->nullable();

            $table->string('tag', 120);
            $table->string('normalized_tag', 120);

            $table->string('name', 180);
            $table->text('description')->nullable();

            $table->string('manufacturer', 150)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 150)->nullable();
            $table->string('asset_code', 120)->nullable();
            $table->string('abc_code', 20)->nullable();

            $table->string('installation_location', 255)->nullable();
            $table->date('commissioned_at')->nullable();

            $table->string('status', 30)
                ->default(EquipmentStatus::Active->value);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'client_id'],
                'equipments_org_client_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('clients')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'client_unit_id'],
                'equipments_org_unit_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('client_units')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'area_id'],
                'equipments_org_area_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('areas')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'subarea_id'],
                'equipments_org_subarea_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('subareas')
                ->restrictOnDelete();

            $table->unique(
                [
                    'organization_id',
                    'client_id',
                    'client_unit_id',
                    'normalized_tag',
                ],
                'equipments_org_client_unit_tag_unique',
            );

            $table->unique(
                ['organization_id', 'id'],
                'equipments_org_id_unique',
            );

            $table->index(
                ['organization_id', 'status', 'name'],
                'equipments_org_status_name_index',
            );

            $table->index(
                ['organization_id', 'normalized_tag'],
                'equipments_org_tag_index',
            );

            $table->index(
                ['organization_id', 'client_unit_id', 'area_id'],
                'equipments_org_unit_area_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};
```

---

## 9.1 Observação sobre `subarea_id`

O MySQL aceita valor `NULL` na chave estrangeira composta.

Quando `subarea_id` for informado, a subárea deverá pertencer à mesma organização.

A validação da cadeia completa continuará na aplicação.

---

# 10. Migration `equipment_documents`

```php
<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_documents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');

            $table->ulid('document_group');

            $table->string('document_type', 50);
            $table->string('title', 200);
            $table->string('document_number', 150)->nullable();
            $table->string('revision', 50)->nullable();
            $table->text('description')->nullable();

            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);

            $table->boolean('is_current')->default(true);

            $table->string('status', 20)
                ->default(DocumentStatus::Active->value);

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->date('issued_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'equipment_id'],
                'equipment_documents_org_equipment_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipments')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'equipment_id', 'document_type'],
                'equipment_documents_org_equipment_type_index',
            );

            $table->index(
                ['organization_id', 'equipment_id', 'document_group'],
                'equipment_documents_org_group_index',
            );

            $table->index(
                ['organization_id', 'equipment_id', 'is_current'],
                'equipment_documents_org_current_index',
            );

            $table->unique(
                ['organization_id', 'equipment_id', 'path'],
                'equipment_documents_org_equipment_path_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_documents');
    }
};
```

---

## 10.1 Limite do `is_current`

O MySQL não resolve de forma simples um índice parcial como:

```text
apenas um is_current = true por document_group
```

Essa regra será garantida pela Action dentro de uma transação.

Também haverá teste de concorrência e integridade.

---

# 11. Model `Equipment`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Equipment extends Model
{
    /** @use HasFactory<\Database\Factories\EquipmentFactory> */
    use BelongsToOrganization;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'client_id',
        'client_unit_id',
        'area_id',
        'subarea_id',
        'tag',
        'normalized_tag',
        'name',
        'description',
        'manufacturer',
        'model',
        'serial_number',
        'asset_code',
        'abc_code',
        'installation_location',
        'commissioned_at',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Equipment $equipment): void {
            $equipment->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'commissioned_at' => 'date',
            'status' => EquipmentStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'client_unit_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function subarea(): BelongsTo
    {
        return $this->belongsTo(Subarea::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class);
    }

    public function currentDocuments(): HasMany
    {
        return $this->documents()
            ->where('is_current', true)
            ->where('status', 'active');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->status === EquipmentStatus::Active;
    }

    public function canReceiveInspection(): bool
    {
        return $this->isActive()
            && $this->client?->isActive() === true
            && $this->unit?->isOperationallyActive() === true
            && $this->area?->isOperationallyActive() === true
            && (
                $this->subarea_id === null
                || $this->subarea?->isOperationallyActive() === true
            );
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

# 12. Model `EquipmentDocument`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\EquipmentDocumentType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class EquipmentDocument extends Model
{
    /** @use HasFactory<\Database\Factories\EquipmentDocumentFactory> */
    use BelongsToOrganization;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'equipment_id',
        'document_group',
        'document_type',
        'title',
        'document_number',
        'revision',
        'description',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'checksum',
        'is_current',
        'status',
        'uploaded_by',
        'issued_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (EquipmentDocument $document): void {
            $document->public_id ??= (string) Str::ulid();
            $document->document_group ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'document_type' => EquipmentDocumentType::class,
            'status' => DocumentStatus::class,
            'is_current' => 'boolean',
            'issued_at' => 'date',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

# 13. Atualizar relacionamentos dos pais

## 13.1 `Client.php`

Adicionar:

```php
public function equipments(): HasMany
{
    return $this->hasMany(Equipment::class);
}
```

## 13.2 `ClientUnit.php`

Adicionar:

```php
public function equipments(): HasMany
{
    return $this->hasMany(Equipment::class);
}
```

## 13.3 `Area.php`

Adicionar:

```php
public function equipments(): HasMany
{
    return $this->hasMany(Equipment::class);
}
```

## 13.4 `Subarea.php`

Adicionar:

```php
public function equipments(): HasMany
{
    return $this->hasMany(Equipment::class);
}
```

---

# 14. Normalizador de TAG

Atualizar:

```text
app/Support/TextNormalizer.php
```

Adicionar:

```php
public static function equipmentTag(string $value): string
{
    return (string) Str::of($value)
        ->trim()
        ->upper()
        ->replaceMatches('/\s+/u', '');
}
```

### Regra

A Action deve salvar:

```php
$normalizedTag = TextNormalizer::equipmentTag($data['tag']);

'tag' => $normalizedTag,
'normalized_tag' => $normalizedTag,
```

No MVP, o TAG exibido será o próprio valor normalizado.

---

# 15. Factory `EquipmentFactory`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EquipmentStatus;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Equipment;
use App\Models\Subarea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
final class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        $tag = fake()->unique()->bothify('EQ-####');

        return [
            'tag' => $tag,
            'normalized_tag' => $tag,
            'name' => fake()->randomElement([
                'Ventilador',
                'Bomba',
                'Transportador',
                'Motor',
                'Redutor',
            ]),
            'description' => null,
            'manufacturer' => fake()->company(),
            'model' => fake()->bothify('MDL-###'),
            'serial_number' => fake()->bothify('SN-########'),
            'asset_code' => null,
            'abc_code' => fake()->randomElement(['A', 'B', 'C']),
            'installation_location' => null,
            'commissioned_at' => null,
            'status' => EquipmentStatus::Active,
            'notes' => null,
        ];
    }

    public function inStructure(
        Client $client,
        ClientUnit $unit,
        Area $area,
        ?Subarea $subarea = null,
    ): static {
        return $this->state(fn (): array => [
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
            'client_unit_id' => $unit->id,
            'area_id' => $area->id,
            'subarea_id' => $subarea?->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (): array => [
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => EquipmentStatus::Inactive,
        ]);
    }

    public function decommissioned(): static
    {
        return $this->state(fn (): array => [
            'status' => EquipmentStatus::Decommissioned,
        ]);
    }
}
```

---

# 16. Factory `EquipmentDocumentFactory`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\EquipmentDocumentType;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EquipmentDocument>
 */
final class EquipmentDocumentFactory extends Factory
{
    protected $model = EquipmentDocument::class;

    public function definition(): array
    {
        return [
            'document_group' => (string) Str::ulid(),
            'document_type' => EquipmentDocumentType::TechnicalDrawing,
            'title' => 'Desenho técnico',
            'document_number' => fake()->bothify('DOC-####'),
            'revision' => '0',
            'description' => null,
            'disk' => 'local',
            'path' => 'testing/'.Str::uuid().'.pdf',
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1024,
            'checksum' => hash('sha256', fake()->uuid()),
            'is_current' => true,
            'status' => DocumentStatus::Active,
            'uploaded_by' => null,
            'issued_at' => null,
        ];
    }

    public function forEquipment(Equipment $equipment): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $equipment->organization_id,
            'equipment_id' => $equipment->id,
        ]);
    }

    public function revisionOf(EquipmentDocument $document): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $document->organization_id,
            'equipment_id' => $document->equipment_id,
            'document_group' => $document->document_group,
            'is_current' => true,
        ]);
    }
}
```

---

# 17. Action `CreateEquipment`

Criar:

```text
app/Actions/Equipments/CreateEquipment.php
```

```php
<?php

declare(strict_types=1);

namespace App\Actions\Equipments;

use App\Enums\EquipmentStatus;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Equipment;
use App\Models\Subarea;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateEquipment
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $actor, array $data): Equipment
    {
        return DB::transaction(function () use ($actor, $data): Equipment {
            $client = Client::query()
                ->forOrganization($this->tenant->id())
                ->findOrFail($data['client_id']);

            $unit = ClientUnit::query()
                ->forOrganization($this->tenant->id())
                ->findOrFail($data['client_unit_id']);

            $area = Area::query()
                ->forOrganization($this->tenant->id())
                ->findOrFail($data['area_id']);

            $subarea = isset($data['subarea_id'])
                ? Subarea::query()
                    ->forOrganization($this->tenant->id())
                    ->findOrFail($data['subarea_id'])
                : null;

            $this->validateHierarchy($client, $unit, $area, $subarea);

            $tag = TextNormalizer::equipmentTag($data['tag']);

            return Equipment::query()->create([
                'organization_id' => $this->tenant->id(),
                'client_id' => $client->id,
                'client_unit_id' => $unit->id,
                'area_id' => $area->id,
                'subarea_id' => $subarea?->id,
                'tag' => $tag,
                'normalized_tag' => $tag,
                'name' => TextNormalizer::text($data['name']),
                'description' => TextNormalizer::nullableText(
                    $data['description'] ?? null,
                ),
                'manufacturer' => TextNormalizer::nullableText(
                    $data['manufacturer'] ?? null,
                ),
                'model' => TextNormalizer::nullableText(
                    $data['model'] ?? null,
                ),
                'serial_number' => TextNormalizer::nullableText(
                    $data['serial_number'] ?? null,
                ),
                'asset_code' => TextNormalizer::technicalCode(
                    $data['asset_code'] ?? null,
                ),
                'abc_code' => TextNormalizer::technicalCode(
                    $data['abc_code'] ?? null,
                ),
                'installation_location' => TextNormalizer::nullableText(
                    $data['installation_location'] ?? null,
                ),
                'commissioned_at' => $data['commissioned_at'] ?? null,
                'status' => EquipmentStatus::Active,
                'notes' => TextNormalizer::nullableText(
                    $data['notes'] ?? null,
                ),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    private function validateHierarchy(
        Client $client,
        ClientUnit $unit,
        Area $area,
        ?Subarea $subarea,
    ): void {
        if (! $client->isActive()) {
            throw ValidationException::withMessages([
                'client_id' => 'O cliente está inativo.',
            ]);
        }

        if (
            $unit->client_id !== $client->id
            || ! $unit->isOperationallyActive()
        ) {
            throw ValidationException::withMessages([
                'client_unit_id' => 'A unidade não pertence ao cliente ou está inativa.',
            ]);
        }

        if (
            $area->client_unit_id !== $unit->id
            || ! $area->isOperationallyActive()
        ) {
            throw ValidationException::withMessages([
                'area_id' => 'A área não pertence à unidade ou está inativa.',
            ]);
        }

        if (
            $subarea !== null
            && (
                $subarea->area_id !== $area->id
                || ! $subarea->isOperationallyActive()
            )
        ) {
            throw ValidationException::withMessages([
                'subarea_id' => 'A subárea não pertence à área ou está inativa.',
            ]);
        }
    }
}
```

---

# 18. Action `UpdateEquipment`

Criar:

```text
app/Actions/Equipments/UpdateEquipment.php
```

Regras:

- equipamento deve pertencer ao tenant;
- validar toda a hierarquia;
- normalizar TAG;
- atualizar `updated_by`;
- não aceitar `organization_id`;
- não aceitar `public_id`;
- não alterar status nessa Action;
- usar transação;
- registrar auditoria futuramente.

---

# 19. Actions de status

Criar:

```text
ActivateEquipment
DeactivateEquipment
DecommissionEquipment
```

### Regras

#### Ativar

Somente quando a estrutura operacional estiver ativa.

#### Inativar

Mantém histórico.

#### Descomissionar

Exige confirmação e justificativa.

No MVP, a justificativa poderá ficar em `notes` ou ser adicionada em auditoria futura.

---

# 20. Armazenamento de documentos

## 20.1 Disco privado

Adicionar em `config/filesystems.php`:

```php
'equipment_documents' => [
    'driver' => 'local',
    'root' => storage_path('app/private/equipment-documents'),
    'throw' => true,
],
```

Em produção, esse disco poderá usar S3 ou armazenamento compatível.

---

## 20.2 Estrutura de caminho

```text
organizations/{organization_id}/equipments/{equipment_public_id}/documents/{document_public_id}/arquivo.ext
```

Exemplo:

```text
organizations/15/equipments/01K.../documents/01K.../U030600-S-551729_R04.pdf
```

---

## 20.3 Tipos permitidos no MVP

- PDF;
- XLSX;
- XLSM;
- DOC;
- DOCX;
- PNG;
- JPG;
- JPEG;
- WEBP.

### Limite inicial

```text
25 MB por documento
```

Esse limite poderá ser configurável futuramente.

---

# 21. Form Request de equipamento

Criar:

```bash
php artisan make:request Equipments/StoreEquipmentRequest
php artisan make:request Equipments/UpdateEquipmentRequest
```

Regras principais:

```php
public function rules(): array
{
    $tenant = app(TenantContext::class);

    return [
        'client_id' => ['required', 'integer'],
        'client_unit_id' => ['required', 'integer'],
        'area_id' => ['required', 'integer'],
        'subarea_id' => ['nullable', 'integer'],

        'tag' => [
            'required',
            'string',
            'max:120',
        ],

        'name' => ['required', 'string', 'max:180'],
        'description' => ['nullable', 'string', 'max:10000'],
        'manufacturer' => ['nullable', 'string', 'max:150'],
        'model' => ['nullable', 'string', 'max:150'],
        'serial_number' => ['nullable', 'string', 'max:150'],
        'asset_code' => ['nullable', 'string', 'max:120'],
        'abc_code' => ['nullable', 'string', 'max:20'],
        'installation_location' => ['nullable', 'string', 'max:255'],
        'commissioned_at' => ['nullable', 'date'],
        'notes' => ['nullable', 'string', 'max:10000'],
    ];
}
```

### Unicidade do TAG

A regra depende de:

```text
organization_id
client_id
client_unit_id
normalized_tag
```

Como `normalized_tag` não vem diretamente do formulário, validar após normalização.

Pode ser feito por:

- regra customizada;
- closure;
- consulta em `after()`;
- Action protegida por índice único.

A validação amigável deve existir, mas o índice único continua obrigatório.

---

# 22. Form Request de documento

Criar:

```bash
php artisan make:request EquipmentDocuments/StoreEquipmentDocumentRequest
```

Regras:

```php
use Illuminate\Validation\Rules\File;

public function rules(): array
{
    return [
        'document_type' => [
            'required',
            Rule::enum(EquipmentDocumentType::class),
        ],
        'title' => ['required', 'string', 'max:200'],
        'document_number' => ['nullable', 'string', 'max:150'],
        'revision' => ['nullable', 'string', 'max:50'],
        'description' => ['nullable', 'string', 'max:10000'],
        'issued_at' => ['nullable', 'date'],
        'document_group' => ['nullable', 'string', 'size:26'],
        'file' => [
            'required',
            File::types([
                'pdf',
                'xlsx',
                'xlsm',
                'doc',
                'docx',
                'png',
                'jpg',
                'jpeg',
                'webp',
            ])->max(25 * 1024),
        ],
    ];
}
```

### Observação

A extensão não deve ser a única validação.

Validar MIME e conteúdo pelo mecanismo do Laravel.

---

# 23. Action `StoreEquipmentDocument`

Criar:

```text
app/Actions/EquipmentDocuments/StoreEquipmentDocument.php
```

Responsabilidades:

1. Validar tenant.
2. Confirmar equipamento.
3. Gerar `public_id`.
4. Definir ou reutilizar `document_group`.
5. Calcular checksum.
6. Armazenar arquivo.
7. Criar registro.
8. Desmarcar revisão anterior.
9. Garantir rollback em falha.
10. Remover arquivo se o banco falhar.

Estrutura conceitual:

```php
public function handle(
    User $actor,
    Equipment $equipment,
    UploadedFile $file,
    array $data,
): EquipmentDocument {
    // validação de tenant
    // geração dos identificadores
    // storage
    // transação
}
```

---

## 23.1 Ordem segura

Como banco e filesystem não compartilham a mesma transação, usar o fluxo:

```text
1. gerar identificadores
2. armazenar arquivo temporário
3. calcular checksum
4. iniciar transação
5. criar registro
6. desmarcar versão anterior
7. commit
8. mover arquivo para destino final
```

Ou:

```text
1. armazenar arquivo final
2. criar registro em transação
3. apagar arquivo em catch
```

Para o MVP, a segunda opção é mais simples.

---

## 23.2 Revisão

Quando `document_group` for informado:

- confirmar que pertence ao mesmo equipamento;
- bloquear documento de outro tenant;
- bloquear grupo de outro equipamento;
- definir nova revisão como atual;
- desmarcar revisões anteriores.

Usar:

```php
DB::transaction(...)
```

e:

```php
lockForUpdate()
```

na consulta do grupo.

---

# 24. Policies

## 24.1 `EquipmentPolicy`

### Membro

Pode:

- listar;
- visualizar;
- visualizar documentos.

### Administrador interno

Pode também:

- criar;
- editar;
- alterar status;
- adicionar documento;
- inativar documento.

### Regras

Sempre validar:

```text
user.organization_id === equipment.organization_id
```

---

## 24.2 `EquipmentDocumentPolicy`

Validar:

- documento pertence à organização;
- equipamento pertence à organização;
- usuário ativo;
- administrador para escrita;
- membro para leitura.

---

# 25. Controllers

Criar:

```bash
php artisan make:controller EquipmentController
php artisan make:controller EquipmentDocumentController
```

---

## 25.1 `EquipmentController`

Métodos:

```text
index
create
store
show
edit
update
updateStatus
```

Não criar `destroy`.

---

## 25.2 `EquipmentDocumentController`

Métodos:

```text
store
show
download
updateStatus
```

No download:

- autorizar;
- localizar arquivo no disco;
- retornar download ou resposta temporária;
- nunca montar caminho a partir da entrada do usuário.

---

# 26. Consultas e filtros

A listagem de equipamentos deve aceitar:

```text
search
client
unit
area
subarea
status
```

A busca deve considerar:

- TAG;
- nome;
- fabricante;
- modelo;
- serial;
- asset code.

Exemplo conceitual:

```php
Equipment::query()
    ->forOrganization($tenant->id())
    ->with([
        'client:id,public_id,name',
        'unit:id,public_id,name',
        'area:id,public_id,name',
        'subarea:id,public_id,name',
    ])
    ->when($search !== '', function ($query) use ($search): void {
        $query->where(function ($query) use ($search): void {
            $query
                ->where('normalized_tag', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('manufacturer', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%");
        });
    })
    ->paginate(20)
    ->withQueryString();
```

---

# 27. Rotas

Dentro do grupo operacional:

```php
Route::resource('equipments', EquipmentController::class)
    ->except(['destroy']);

Route::patch(
    'equipments/{equipment}/status',
    [EquipmentController::class, 'updateStatus'],
)->name('equipments.status');

Route::post(
    'equipments/{equipment}/documents',
    [EquipmentDocumentController::class, 'store'],
)->name('equipments.documents.store');

Route::get(
    'equipment-documents/{equipmentDocument}',
    [EquipmentDocumentController::class, 'show'],
)->name('equipment-documents.show');

Route::get(
    'equipment-documents/{equipmentDocument}/download',
    [EquipmentDocumentController::class, 'download'],
)->name('equipment-documents.download');

Route::patch(
    'equipment-documents/{equipmentDocument}/status',
    [EquipmentDocumentController::class, 'updateStatus'],
)->name('equipment-documents.status');
```

---

# 28. Páginas Vue

Criar:

```text
resources/js/pages/Equipments/Index.vue
resources/js/pages/Equipments/Create.vue
resources/js/pages/Equipments/Edit.vue
resources/js/pages/Equipments/Show.vue
```

Componentes de domínio:

```text
resources/js/components/domain/equipments/EquipmentForm.vue
resources/js/components/domain/equipments/EquipmentStatusBadge.vue
resources/js/components/domain/equipments/EquipmentDocumentList.vue
resources/js/components/domain/equipments/EquipmentDocumentUpload.vue
resources/js/components/domain/equipments/EquipmentLocationBreadcrumb.vue
```

---

## 28.1 Tela de listagem

Mostrar:

- TAG;
- nome;
- cliente;
- unidade;
- área;
- subárea;
- status;
- quantidade de inspeções futuramente;
- ações permitidas.

No celular:

- usar cards ou tabela responsiva;
- priorizar TAG e nome;
- filtros recolhíveis.

---

## 28.2 Formulário de equipamento

Campos:

```text
Cliente
Unidade
Área
Subárea
TAG
Nome
Descrição
Fabricante
Modelo
Número de série
Código patrimonial
Código ABC
Local de instalação
Data de comissionamento
Observações
```

Campos dependentes:

```text
Cliente selecionado
→ carrega unidades

Unidade selecionada
→ carrega áreas

Área selecionada
→ carrega subáreas
```

Essas opções devem vir filtradas pelo tenant.

---

## 28.3 Tela de detalhes

Abas sugeridas:

```text
Resumo
Documentos
Inspeções
Histórico
```

No MVP desta etapa:

- Resumo;
- Documentos.

As demais abas poderão aparecer desabilitadas ou ser adicionadas depois.

---

# 29. Testes obrigatórios

Criar:

```text
tests/Feature/Equipments/
```

---

## 29.1 Cadastro

Testar:

- administrador cria equipamento;
- membro não cria;
- tenant é definido pelo backend;
- `organization_id` enviado é ignorado;
- TAG é normalizado;
- TAG é único por unidade;
- mesmo TAG é permitido em unidade diferente;
- mesmo TAG é permitido em cliente diferente;
- mesmo TAG é permitido em organização diferente;
- não cria em cliente inativo;
- não cria em unidade inativa;
- não cria em área inativa;
- não cria em subárea inativa;
- não cria com hierarquia cruzada.

---

## 29.2 Acesso

Testar:

- usuário visualiza equipamento da própria organização;
- usuário não visualiza equipamento de outra organização;
- usuário não edita equipamento de outra organização;
- route model binding usa `public_id`;
- `id` interno não é exposto como identificador principal.

---

## 29.3 Status

Testar:

- equipamento ativo pode receber inspeção futuramente;
- inativo continua visível;
- descomissionado continua no histórico;
- TAG não é reutilizado;
- membro não altera status.

---

## 29.4 Documentos

Testar:

- administrador envia documento;
- membro não envia;
- arquivo é armazenado no disco privado;
- registro recebe checksum;
- documento pertence ao equipamento correto;
- documento de outra organização não é acessível;
- nova revisão desmarca a anterior;
- revisão antiga permanece disponível;
- grupo de outro equipamento é rejeitado;
- arquivo inválido é rejeitado;
- arquivo acima do limite é rejeitado;
- download exige autorização.

---

## 29.5 Filesystem fake

Usar:

```php
Storage::fake('equipment_documents');
```

Exemplo:

```php
Storage::disk('equipment_documents')
    ->assertExists($document->path);
```

---

# 30. Seed de demonstração

Atualizar `DevelopmentSeeder` para criar:

```text
Cliente: Samarco Mineração S.A.
Unidade: Ubu
Área: Usina III
Subárea: Forno de Endurecimento
Equipamento: Ventilador
TAG: U03-06VT002
Código ABC: A
```

Usar `firstOrCreate()` no escopo correto.

Não duplicar ao executar novamente.

---

# 31. Validação manual

1. Entrar como administrador.
2. Cadastrar equipamento.
3. Confirmar normalização do TAG.
4. Tentar cadastrar o mesmo TAG na mesma unidade.
5. Confirmar bloqueio.
6. Cadastrar mesmo TAG em outra unidade.
7. Confirmar permissão.
8. Anexar PDF.
9. Criar nova revisão do mesmo documento.
10. Confirmar somente uma revisão atual.
11. Baixar documento.
12. Inativar equipamento.
13. Confirmar que continua consultável.
14. Entrar como membro.
15. Confirmar acesso somente leitura.

---

# 32. Comandos finais

```bash
php artisan migrate
vendor/bin/pint --dirty
php artisan test
npm run build
php artisan route:list
```

Caso ainda seja seguro limpar o banco:

```bash
php artisan migrate:fresh --seed
```

---

# 33. Critérios de aceite

- [ ] tabela `equipments` criada;
- [ ] tabela `equipment_documents` criada;
- [ ] equipamento possui `public_id`;
- [ ] documento possui `public_id`;
- [ ] TAG é normalizado;
- [ ] TAG é único por unidade;
- [ ] hierarquia é validada;
- [ ] estrutura inativa bloqueia novo equipamento;
- [ ] equipamento inativo permanece no histórico;
- [ ] descomissionamento não apaga dados;
- [ ] upload privado funciona;
- [ ] checksum é salvo;
- [ ] documentos possuem revisão;
- [ ] apenas uma revisão fica atual;
- [ ] documento antigo continua disponível;
- [ ] usuário de outra organização não acessa;
- [ ] membro possui leitura;
- [ ] administrador possui escrita;
- [ ] testes passam;
- [ ] build passa;
- [ ] documentação corresponde ao código.

---

# 34. Riscos e brechas

## 34.1 TAG alterado depois das inspeções

Mudar o TAG atual não pode alterar relatórios antigos.

Mitigação:

- inspeções futuras terão snapshot;
- relatórios usarão o snapshot;
- mudança cadastral será auditada.

---

## 34.2 Hierarquia cruzada

IDs válidos individualmente podem pertencer a pais diferentes.

Mitigação:

- validação da cadeia;
- constraints compostas;
- testes diretos.

---

## 34.3 Documento marcado como atual em duplicidade

Duas requisições simultâneas podem tentar criar revisão atual.

Mitigação:

- transação;
- `lockForUpdate()`;
- teste de concorrência;
- futura constraint adicional, se necessária.

---

## 34.4 Arquivo salvo sem registro

Falha no banco após upload pode deixar arquivo órfão.

Mitigação:

- `try/catch`;
- apagar arquivo ao falhar;
- rotina futura de limpeza.

---

## 34.5 Registro salvo sem arquivo

Falha no storage após registro pode gerar documento inválido.

Mitigação:

- armazenar antes de criar registro;
- só persistir após sucesso do upload.

---

## 34.6 Nome de arquivo malicioso

Nunca usar o nome original como caminho final sem sanitização.

Mitigação:

- gerar nome interno;
- guardar nome original apenas como metadado;
- validar MIME;
- impedir execução pública.

---

## 34.7 Upload grande

Arquivos grandes podem exceder:

- `upload_max_filesize`;
- `post_max_size`;
- timeout;
- proxy;
- servidor web.

Mitigação:

- limite inicial de 25 MB;
- configurar PHP e servidor;
- feedback claro na interface.

---

## 34.8 Equipamento duplicado com TAG diferente

Um mesmo equipamento pode ser cadastrado novamente com erro de TAG.

Mitigação futura:

- alertar por número de série;
- alertar por asset code;
- buscar registros parecidos.

Não tornar serial único sem confirmação da regra de negócio.

---

# 35. Checklist de execução

- [ ] Criar enums.
- [ ] Criar models e migrations.
- [ ] Revisar constraints.
- [ ] Atualizar relacionamentos.
- [ ] Criar normalização do TAG.
- [ ] Criar factories.
- [ ] Criar Actions de equipamento.
- [ ] Criar Actions de status.
- [ ] Configurar disco privado.
- [ ] Criar Action de documento.
- [ ] Criar Form Requests.
- [ ] Criar Policies.
- [ ] Criar Controllers.
- [ ] Criar rotas.
- [ ] Criar páginas Vue.
- [ ] Criar filtros.
- [ ] Criar testes de cadastro.
- [ ] Criar testes de isolamento.
- [ ] Criar testes de documento.
- [ ] Atualizar seeder.
- [ ] Executar migrations.
- [ ] Executar Pint.
- [ ] Executar testes.
- [ ] Executar build.
- [ ] Validar manualmente.
- [ ] Atualizar roadmap.
- [ ] Criar commit.

---

# 36. Commit sugerido

```bash
git add .
git commit -m "feat: add equipment and technical document management"
```

---

# 37. Próximo documento

```text
06-INSPECOES-E-FLUXO.md
```

O próximo documento definirá:

- inspeções;
- inspeção inicial;
- reinspeção;
- responsáveis;
- estados;
- transições;
- snapshots;
- histórico de status;
- revisão;
- aprovação;
- liberação;
- testes.
