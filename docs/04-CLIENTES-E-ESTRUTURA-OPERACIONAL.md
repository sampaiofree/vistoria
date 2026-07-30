# 04 — Clientes e Estrutura Operacional

## 1. Objetivo

Implementar a hierarquia operacional que localiza os equipamentos dentro dos clientes atendidos por cada organização.

A estrutura será:

```text
Organização
└── Cliente
    └── Unidade
        └── Área
            └── Subárea
```

Exemplo:

```text
Empresa de Inspeção
└── Samarco Mineração
    └── Unidade de Ubu
        └── Usina III
            └── Forno de Endurecimento
```

Essa hierarquia será usada posteriormente por:

- equipamentos;
- inspeções;
- filtros;
- relatórios;
- permissões;
- dashboards;
- históricos.

---

## 2. Resultado esperado

Ao concluir esta etapa, o administrador interno deverá conseguir:

- cadastrar clientes;
- editar clientes;
- ativar e inativar clientes;
- cadastrar unidades dentro de um cliente;
- cadastrar áreas dentro de uma unidade;
- cadastrar subáreas dentro de uma área;
- visualizar a hierarquia;
- pesquisar e filtrar cadastros;
- navegar pelo sistema no celular;
- impedir que dados de uma organização sejam vinculados a outra.

Membros comuns poderão consultar a estrutura operacional, mas não alterá-la.

---

## 3. Escopo incluído

Será criado:

- tabela `clients`;
- tabela `client_units`;
- tabela `areas`;
- tabela `subareas`;
- enum de status cadastral;
- normalização de documento e códigos;
- models e relacionamentos;
- factories;
- actions de criação e atualização;
- policies;
- form requests;
- controllers Inertia;
- rotas;
- páginas Vue;
- testes de CRUD;
- testes de isolamento multiempresa;
- testes de integridade hierárquica.

---

## 4. Fora do escopo

Não será criado agora:

- equipamentos;
- documentos de equipamentos;
- inspeções;
- permissões por cliente ou unidade;
- usuários externos dos clientes;
- importação em massa;
- geolocalização;
- mapas;
- logotipo do cliente;
- contatos múltiplos;
- exclusão definitiva pela interface;
- editor de organograma.

---

## 5. Regras de negócio

### 5.1 Cliente pertence a uma organização

```text
clients.organization_id
```

O mesmo cliente comercial poderá existir em organizações diferentes.

Exemplo:

```text
Organização A → Samarco Mineração
Organização B → Samarco Mineração
```

Esses registros são independentes.

---

### 5.2 Unidade pertence a um cliente

Uma unidade não pode existir sem cliente.

```text
client_units.client_id
```

---

### 5.3 Área pertence a uma unidade

Uma área não pode existir sem unidade.

```text
areas.client_unit_id
```

---

### 5.4 Subárea pertence a uma área

Uma subárea não pode existir sem área.

```text
subareas.area_id
```

---

### 5.5 Organização repetida nas tabelas

Todas as tabelas terão:

```text
organization_id
```

Mesmo quando a organização puder ser inferida pelo registro pai.

Isso é intencional e serve para:

- filtrar dados com segurança;
- facilitar consultas;
- impedir relações cruzadas;
- tornar Jobs independentes da sessão;
- criar índices eficientes;
- detectar inconsistências.

---

### 5.6 Integridade hierárquica

O banco deve impedir situações como:

```text
Unidade da Organização A
vinculada a cliente da Organização B
```

Por isso, serão usadas chaves estrangeiras compostas.

---

### 5.7 Status

Todos os cadastros terão:

```text
active
inactive
```

Inativar um registro:

- não apaga histórico;
- não inativa automaticamente os descendentes;
- impede seu uso em novos equipamentos e inspeções;
- mantém consultas e relatórios antigos funcionando.

Exemplo:

```text
Cliente inativo
├── unidades continuam no histórico
├── equipamentos antigos continuam acessíveis
└── novas inspeções não podem usar esse cliente
```

---

### 5.8 Exclusão

Não haverá botão de exclusão definitiva no MVP.

A interface usará ativação e inativação.

`softDeletes()` será mantido como proteção administrativa, mas não será o fluxo comum.

---

### 5.9 Documento do cliente

O documento:

- é opcional;
- deve armazenar somente números;
- é único dentro da organização quando preenchido;
- pode se repetir em outra organização.

---

### 5.10 Códigos

Unidades, áreas e subáreas poderão possuir código técnico opcional.

O código será normalizado:

```text
" u03 "       → "U03"
" usina 03 "  → "USINA03"
```

O valor original exibido será o código já limpo e padronizado.

---

### 5.11 Unicidade

#### Cliente

```text
organization_id + document
```

Apenas quando o documento estiver preenchido.

#### Unidade

```text
organization_id + client_id + normalized_code
```

Quando houver código.

#### Área

```text
organization_id + client_unit_id + normalized_code
```

Quando houver código.

#### Subárea

```text
organization_id + area_id + normalized_code
```

Quando houver código.

Nomes iguais poderão existir quando os códigos forem diferentes ou não informados.

---

### 5.12 Reutilização de códigos

Um código pertencente a um registro inativo não será reutilizado criando outro registro.

O fluxo correto será reativar o registro antigo.

Isso evita duas identidades históricas com o mesmo código.

---

## 6. Modelo de dados

```text
organizations
└── clients
    └── client_units
        └── areas
            └── subareas
```

### Relacionamentos

```text
Organization hasMany Client
Organization hasMany ClientUnit
Organization hasMany Area
Organization hasMany Subarea

Client belongsTo Organization
Client hasMany ClientUnit

ClientUnit belongsTo Organization
ClientUnit belongsTo Client
ClientUnit hasMany Area

Area belongsTo Organization
Area belongsTo ClientUnit
Area hasMany Subarea

Subarea belongsTo Organization
Subarea belongsTo Area
```

---

## 7. Estrutura de arquivos

```text
app/
├── Actions/
│   ├── Clients/
│   ├── ClientUnits/
│   ├── Areas/
│   └── Subareas/
├── Enums/
│   └── RegistrationStatus.php
├── Http/
│   ├── Controllers/
│   │   ├── ClientController.php
│   │   ├── ClientUnitController.php
│   │   ├── AreaController.php
│   │   └── SubareaController.php
│   ├── Requests/
│   │   ├── Clients/
│   │   ├── ClientUnits/
│   │   ├── Areas/
│   │   └── Subareas/
│   └── Resources/
├── Models/
│   ├── Concerns/
│   │   └── BelongsToOrganization.php
│   ├── Client.php
│   ├── ClientUnit.php
│   ├── Area.php
│   └── Subarea.php
├── Policies/
│   ├── ClientPolicy.php
│   ├── ClientUnitPolicy.php
│   ├── AreaPolicy.php
│   └── SubareaPolicy.php
└── Support/
    └── TextNormalizer.php

resources/js/pages/
├── Clients/
├── ClientUnits/
├── Areas/
└── Subareas/

tests/Feature/
└── OperationalStructure/
```

---

# 8. Etapa 1 — Criar enum de status

Criar:

```text
app/Enums/RegistrationStatus.php
```

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum RegistrationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
```

---

# 9. Etapa 2 — Criar normalizador

Criar:

```text
app/Support/TextNormalizer.php
```

```php
<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class TextNormalizer
{
    public static function text(string $value): string
    {
        return (string) Str::of($value)
            ->trim()
            ->replaceMatches('/\s+/u', ' ');
    }

    public static function nullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::text($value);

        return $normalized === '' ? null : $normalized;
    }

    public static function document(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $value);

        return $normalized === '' ? null : $normalized;
    }

    public static function technicalCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = (string) Str::of($value)
            ->trim()
            ->upper()
            ->replaceMatches('/\s+/u', '');

        return $normalized === '' ? null : $normalized;
    }

    public static function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }
}
```

### Observação

Não remover:

- hífens;
- barras;
- pontos técnicos;
- letras.

A normalização de código remove apenas espaços e padroniza maiúsculas.

---

# 10. Etapa 3 — Trait de vínculo com organização

Criar:

```text
app/Models/Concerns/BelongsToOrganization.php
```

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(
        Builder $query,
        int $organizationId,
    ): Builder {
        return $query->where(
            $query->getModel()->qualifyColumn('organization_id'),
            $organizationId,
        );
    }

    public function belongsToOrganization(int $organizationId): bool
    {
        return (int) $this->organization_id === $organizationId;
    }
}
```

### Limite da trait

Essa trait:

- fornece relacionamento;
- fornece scope explícito;
- verifica pertencimento.

Ela não:

- aplica Global Scope;
- define automaticamente o tenant;
- substitui Policy;
- substitui filtro nas consultas.

---

# 11. Etapa 4 — Criar models e migrations

## 11.1 Comandos

```bash
php artisan make:model Client -mf
php artisan make:model ClientUnit -mf
php artisan make:model Area -mf
php artisan make:model Subarea -mf
```

A ordem das migrations precisa ser:

```text
clients
client_units
areas
subareas
```

Caso os timestamps não respeitem essa ordem, renomear os arquivos antes de executar.

---

# 12. Migration `clients`

```php
<?php

declare(strict_types=1);

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('document', 20)->nullable();

            $table->string('email', 254)->nullable();
            $table->string('phone', 30)->nullable();

            $table->string('status', 20)
                ->default(RegistrationStatus::Active->value);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'document'],
                'clients_org_document_unique',
            );

            // Necessário para as chaves estrangeiras compostas dos descendentes.
            $table->unique(
                ['organization_id', 'id'],
                'clients_org_id_unique',
            );

            $table->index(
                ['organization_id', 'status', 'name'],
                'clients_org_status_name_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
```

### Observação sobre documento nulo

No MySQL, o índice único permite vários valores `NULL`.

Portanto, vários clientes sem documento poderão existir.

---

# 13. Migration `client_units`

```php
<?php

declare(strict_types=1);

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_units', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('client_id');

            $table->string('name', 150);
            $table->string('code', 80)->nullable();
            $table->string('normalized_code', 80)->nullable();

            $table->string('timezone', 64)->nullable();

            $table->string('address_line', 200)->nullable();
            $table->string('address_number', 30)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->char('country_code', 2)->default('BR');

            $table->string('status', 20)
                ->default(RegistrationStatus::Active->value);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'client_id'],
                'client_units_org_client_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('clients')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'client_id', 'normalized_code'],
                'client_units_org_client_code_unique',
            );

            $table->unique(
                ['organization_id', 'id'],
                'client_units_org_id_unique',
            );

            $table->index(
                ['organization_id', 'client_id', 'status', 'name'],
                'client_units_org_client_status_name_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_units');
    }
};
```

---

# 14. Migration `areas`

```php
<?php

declare(strict_types=1);

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('client_unit_id');

            $table->string('name', 150);
            $table->string('code', 80)->nullable();
            $table->string('normalized_code', 80)->nullable();

            $table->string('status', 20)
                ->default(RegistrationStatus::Active->value);

            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'client_unit_id'],
                'areas_org_unit_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('client_units')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'client_unit_id', 'normalized_code'],
                'areas_org_unit_code_unique',
            );

            $table->unique(
                ['organization_id', 'id'],
                'areas_org_id_unique',
            );

            $table->index(
                ['organization_id', 'client_unit_id', 'status', 'name'],
                'areas_org_unit_status_name_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
```

---

# 15. Migration `subareas`

```php
<?php

declare(strict_types=1);

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subareas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('area_id');

            $table->string('name', 150);
            $table->string('code', 80)->nullable();
            $table->string('normalized_code', 80)->nullable();

            $table->string('status', 20)
                ->default(RegistrationStatus::Active->value);

            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'area_id'],
                'subareas_org_area_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('areas')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'area_id', 'normalized_code'],
                'subareas_org_area_code_unique',
            );

            $table->index(
                ['organization_id', 'area_id', 'status', 'name'],
                'subareas_org_area_status_name_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subareas');
    }
};
```

---

## 15.1 Brecha de códigos nulos

O MySQL permite vários `NULL` em índices únicos.

Isso é desejado, porque o código é opcional.

Quando houver código, a aplicação deve preencher simultaneamente:

```text
code
normalized_code
```

Quando não houver código:

```text
code = null
normalized_code = null
```

---

# 16. Models

## 16.1 `Client.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use BelongsToOrganization;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'legal_name',
        'document',
        'email',
        'phone',
        'status',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Client $client): void {
            $client->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(ClientUnit::class);
    }

    public function isActive(): bool
    {
        return $this->status === RegistrationStatus::Active;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

## 16.2 `ClientUnit.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class ClientUnit extends Model
{
    /** @use HasFactory<\Database\Factories\ClientUnitFactory> */
    use BelongsToOrganization;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'client_id',
        'name',
        'code',
        'normalized_code',
        'timezone',
        'address_line',
        'address_number',
        'district',
        'postal_code',
        'city',
        'state',
        'country_code',
        'status',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClientUnit $unit): void {
            $unit->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function isActive(): bool
    {
        return $this->status === RegistrationStatus::Active;
    }

    public function isOperationallyActive(): bool
    {
        return $this->isActive()
            && $this->client !== null
            && $this->client->isActive();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

## 16.3 `Area.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Area extends Model
{
    /** @use HasFactory<\Database\Factories\AreaFactory> */
    use BelongsToOrganization;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'client_unit_id',
        'name',
        'code',
        'normalized_code',
        'status',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (Area $area): void {
            $area->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'client_unit_id');
    }

    public function subareas(): HasMany
    {
        return $this->hasMany(Subarea::class);
    }

    public function isActive(): bool
    {
        return $this->status === RegistrationStatus::Active;
    }

    public function isOperationallyActive(): bool
    {
        return $this->isActive()
            && $this->unit !== null
            && $this->unit->isOperationallyActive();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

## 16.4 `Subarea.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Subarea extends Model
{
    /** @use HasFactory<\Database\Factories\SubareaFactory> */
    use BelongsToOrganization;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'area_id',
        'name',
        'code',
        'normalized_code',
        'status',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subarea $subarea): void {
            $subarea->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function isActive(): bool
    {
        return $this->status === RegistrationStatus::Active;
    }

    public function isOperationallyActive(): bool
    {
        return $this->isActive()
            && $this->area !== null
            && $this->area->isOperationallyActive();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

# 17. Atualizar `Organization.php`

Adicionar:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function clients(): HasMany
{
    return $this->hasMany(Client::class);
}

public function clientUnits(): HasMany
{
    return $this->hasMany(ClientUnit::class);
}

public function areas(): HasMany
{
    return $this->hasMany(Area::class);
}

public function subareas(): HasMany
{
    return $this->hasMany(Subarea::class);
}
```

---

# 18. Factories

## 18.1 `ClientFactory`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
final class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company(),
            'legal_name' => fake()->company().' LTDA',
            'document' => fake()->unique()->numerify('##############'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => RegistrationStatus::Active,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Inactive,
        ]);
    }
}
```

---

## 18.2 `ClientUnitFactory`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Models\ClientUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientUnit>
 */
final class ClientUnitFactory extends Factory
{
    protected $model = ClientUnit::class;

    public function definition(): array
    {
        $code = fake()->unique()->bothify('UN-###');

        return [
            'organization_id' => fn (array $attributes): int => Client::findOrFail(
                $attributes['client_id'],
            )->organization_id,
            'client_id' => Client::factory(),
            'name' => 'Unidade '.fake()->city(),
            'code' => $code,
            'normalized_code' => $code,
            'timezone' => 'America/Sao_Paulo',
            'address_line' => fake()->streetName(),
            'address_number' => fake()->buildingNumber(),
            'district' => fake()->word(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'country_code' => 'BR',
            'status' => RegistrationStatus::Active,
            'notes' => null,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Inactive,
        ]);
    }
}
```

### Atenção

Factories com relacionamento composto podem exigir ajustes conforme a forma de criação usada nos testes.

Preferir nos testes:

```php
$client = Client::factory()->create();

$unit = ClientUnit::factory()
    ->forClient($client)
    ->create();
```

---

## 18.3 `AreaFactory`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Area;
use App\Models\ClientUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
final class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'client_unit_id' => ClientUnit::factory(),
            'name' => 'Área '.fake()->word(),
            'code' => null,
            'normalized_code' => null,
            'status' => RegistrationStatus::Active,
            'description' => null,
        ];
    }

    public function forUnit(ClientUnit $unit): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $unit->organization_id,
            'client_unit_id' => $unit->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Inactive,
        ]);
    }
}
```

---

## 18.4 `SubareaFactory`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Area;
use App\Models\Subarea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subarea>
 */
final class SubareaFactory extends Factory
{
    protected $model = Subarea::class;

    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'name' => 'Subárea '.fake()->word(),
            'code' => null,
            'normalized_code' => null,
            'status' => RegistrationStatus::Active,
            'description' => null,
        ];
    }

    public function forArea(Area $area): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $area->organization_id,
            'area_id' => $area->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Inactive,
        ]);
    }
}
```

---

# 19. Actions

## 19.1 Princípio

O `organization_id` deve vir do `TenantContext`.

Nunca aceitar:

```php
$request->organization_id
```

---

## 19.2 Criar cliente

Criar:

```text
app/Actions/Clients/CreateClient.php
```

```php
<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;

final class CreateClient
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $actor, array $data): Client
    {
        return Client::query()->create([
            'organization_id' => $this->tenant->id(),
            'name' => TextNormalizer::text($data['name']),
            'legal_name' => TextNormalizer::nullableText(
                $data['legal_name'] ?? null,
            ),
            'document' => TextNormalizer::document(
                $data['document'] ?? null,
            ),
            'email' => TextNormalizer::email($data['email'] ?? null),
            'phone' => TextNormalizer::nullableText($data['phone'] ?? null),
            'status' => RegistrationStatus::Active,
            'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
        ]);
    }
}
```

---

## 19.3 Criar unidade

Criar:

```text
app/Actions/ClientUnits/CreateClientUnit.php
```

```php
<?php

declare(strict_types=1);

namespace App\Actions\ClientUnits;

use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Validation\ValidationException;

final class CreateClientUnit
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(
        User $actor,
        Client $client,
        array $data,
    ): ClientUnit {
        if (! $client->belongsToOrganization($this->tenant->id())) {
            abort(404);
        }

        if (! $client->isActive()) {
            throw ValidationException::withMessages([
                'client' => 'Não é possível cadastrar unidade em cliente inativo.',
            ]);
        }

        $code = TextNormalizer::technicalCode($data['code'] ?? null);

        return ClientUnit::query()->create([
            'organization_id' => $this->tenant->id(),
            'client_id' => $client->id,
            'name' => TextNormalizer::text($data['name']),
            'code' => $code,
            'normalized_code' => $code,
            'timezone' => TextNormalizer::nullableText(
                $data['timezone'] ?? null,
            ),
            'address_line' => TextNormalizer::nullableText(
                $data['address_line'] ?? null,
            ),
            'address_number' => TextNormalizer::nullableText(
                $data['address_number'] ?? null,
            ),
            'district' => TextNormalizer::nullableText(
                $data['district'] ?? null,
            ),
            'postal_code' => TextNormalizer::document(
                $data['postal_code'] ?? null,
            ),
            'city' => TextNormalizer::nullableText($data['city'] ?? null),
            'state' => TextNormalizer::nullableText($data['state'] ?? null),
            'country_code' => mb_strtoupper(
                $data['country_code'] ?? 'BR',
            ),
            'status' => RegistrationStatus::Active,
            'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
        ]);
    }
}
```

---

## 19.4 Criar área e subárea

Seguir o mesmo padrão:

```text
CreateArea
CreateSubarea
```

Validações obrigatórias:

- pai pertence ao tenant;
- pai está operacionalmente ativo;
- `organization_id` definido pelo backend;
- código normalizado;
- status inicial ativo.

---

## 19.5 Atualizações

Criar Actions separadas:

```text
UpdateClient
UpdateClientUnit
UpdateArea
UpdateSubarea
```

Regras:

- validar organização;
- não permitir alterar o pai por uma edição simples;
- normalizar todos os campos;
- não permitir alterar `organization_id`;
- não permitir alterar `public_id`;
- não permitir alteração silenciosa de status.

---

## 19.6 Alteração de status

Criar Actions explícitas:

```text
ActivateClient
DeactivateClient
ActivateClientUnit
DeactivateClientUnit
ActivateArea
DeactivateArea
ActivateSubarea
DeactivateSubarea
```

Ou uma Action pequena por entidade com status informado, desde que a autorização continue explícita.

Não usar um campo genérico no formulário de edição para alterar o status sem controle.

---

# 20. Form Requests

## 20.1 Cliente

Criar:

```bash
php artisan make:request Clients/StoreClientRequest
php artisan make:request Clients/UpdateClientRequest
```

### Regras principais

```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    $organizationId = app(TenantContext::class)->id();

    return [
        'name' => ['required', 'string', 'max:150'],
        'legal_name' => ['nullable', 'string', 'max:200'],
        'document' => [
            'nullable',
            'string',
            'max:20',
            Rule::unique('clients', 'document')
                ->where('organization_id', $organizationId),
        ],
        'email' => ['nullable', 'email:rfc', 'max:254'],
        'phone' => ['nullable', 'string', 'max:30'],
        'notes' => ['nullable', 'string', 'max:5000'],
    ];
}
```

### Problema

A validação de unicidade deve usar o documento já normalizado.

Portanto, no `prepareForValidation()`:

```php
protected function prepareForValidation(): void
{
    $this->merge([
        'document' => TextNormalizer::document($this->input('document')),
        'email' => TextNormalizer::email($this->input('email')),
    ]);
}
```

No update, usar:

```php
->ignore($this->route('client')->id)
```

---

## 20.2 Unidade

Criar:

```bash
php artisan make:request ClientUnits/StoreClientUnitRequest
php artisan make:request ClientUnits/UpdateClientUnitRequest
```

Regras principais:

```text
name obrigatório
code opcional e único dentro do cliente
timezone válida quando preenchida
country_code com 2 caracteres
campos de endereço opcionais
```

No `prepareForValidation()`:

```php
$this->merge([
    'code' => TextNormalizer::technicalCode($this->input('code')),
    'postal_code' => TextNormalizer::document($this->input('postal_code')),
    'country_code' => mb_strtoupper(
        $this->input('country_code', 'BR'),
    ),
]);
```

A regra de unicidade usa:

```text
organization_id
client_id
normalized_code
```

Como o formulário envia `code`, a consulta deve comparar o valor normalizado com `normalized_code`.

---

## 20.3 Área e subárea

Aplicar o mesmo padrão:

```text
StoreAreaRequest
UpdateAreaRequest
StoreSubareaRequest
UpdateSubareaRequest
```

---

# 21. Policies

## 21.1 Regra inicial

### Superadministrador

Não opera automaticamente dentro das organizações.

As rotas operacionais exigem tenant resolvido.

### Administrador interno

Pode:

- visualizar;
- criar;
- editar;
- ativar;
- inativar.

### Membro

Pode:

- visualizar listagens;
- visualizar detalhes;
- usar os registros em fluxos autorizados posteriormente.

Não pode:

- criar;
- editar;
- ativar;
- inativar.

---

## 21.2 Exemplo `ClientPolicy`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

final class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    public function view(User $user, Client $client): bool
    {
        return $this->sameOrganization($user, $client);
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $user->organization_id !== null;
    }

    public function update(User $user, Client $client): bool
    {
        return $user->isCompanyAdmin()
            && $this->sameOrganization($user, $client);
    }

    public function changeStatus(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }

    private function sameOrganization(User $user, Client $client): bool
    {
        return $user->organization_id !== null
            && $client->belongsToOrganization($user->organization_id);
    }
}
```

Criar policies equivalentes para:

```text
ClientUnit
Area
Subarea
```

Cada uma deve verificar o tenant do recurso.

---

# 22. Controllers

Criar:

```bash
php artisan make:controller ClientController
php artisan make:controller ClientUnitController
php artisan make:controller AreaController
php artisan make:controller SubareaController
```

Controllers devem:

- autorizar;
- consultar apenas o tenant atual;
- chamar Actions;
- retornar páginas Inertia;
- redirecionar com mensagem;
- não conter regra de normalização;
- não aceitar `organization_id`.

---

## 22.1 Consulta de clientes

Exemplo:

```php
public function index(Request $request, TenantContext $tenant): Response
{
    $this->authorize('viewAny', Client::class);

    $search = trim((string) $request->string('search'));

    $clients = Client::query()
        ->forOrganization($tenant->id())
        ->when(
            $search !== '',
            fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%");
            }),
        )
        ->withCount('units')
        ->orderBy('name')
        ->paginate(20)
        ->withQueryString();

    return Inertia::render('Clients/Index', [
        'clients' => $clients,
        'filters' => [
            'search' => $search,
        ],
        'can' => [
            'create' => $request->user()->can('create', Client::class),
        ],
    ]);
}
```

### Atenção

A busca com `LIKE "%texto%"` pode ficar cara em bases grandes.

Para o MVP é aceitável.

Depois poderão ser criados:

- índices auxiliares;
- busca por prefixo;
- pesquisa especializada.

---

## 22.2 Exibição hierárquica

A página do cliente deve carregar:

- cliente;
- unidades paginadas;
- contagem de áreas;
- status;
- permissões disponíveis.

Não carregar toda a árvore de uma vez em clientes grandes.

---

# 23. Rotas

Dentro do grupo já protegido por:

```text
auth
verified
user.active
organization.active
tenant
```

Adicionar:

```php
use App\Http\Controllers\AreaController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientUnitController;
use App\Http\Controllers\SubareaController;

Route::resource('clients', ClientController::class)
    ->except(['destroy']);

Route::patch(
    'clients/{client}/status',
    [ClientController::class, 'updateStatus'],
)->name('clients.status');

Route::scopeBindings()->group(function (): void {
    Route::resource('clients.units', ClientUnitController::class)
        ->shallow()
        ->except(['destroy']);

    Route::patch(
        'units/{unit}/status',
        [ClientUnitController::class, 'updateStatus'],
    )->name('units.status');

    Route::resource('units.areas', AreaController::class)
        ->shallow()
        ->except(['destroy']);

    Route::patch(
        'areas/{area}/status',
        [AreaController::class, 'updateStatus'],
    )->name('areas.status');

    Route::resource('areas.subareas', SubareaController::class)
        ->shallow()
        ->except(['destroy']);

    Route::patch(
        'subareas/{subarea}/status',
        [SubareaController::class, 'updateStatus'],
    )->name('subareas.status');
});
```

### Observação

Confirmar os nomes gerados com:

```bash
php artisan route:list
```

Se os parâmetros gerados pelo resource não coincidirem com:

```text
unit
area
subarea
```

definir os parâmetros explicitamente.

---

# 24. Páginas Vue

Criar no mínimo:

```text
resources/js/pages/Clients/Index.vue
resources/js/pages/Clients/Create.vue
resources/js/pages/Clients/Edit.vue
resources/js/pages/Clients/Show.vue

resources/js/pages/ClientUnits/Create.vue
resources/js/pages/ClientUnits/Edit.vue
resources/js/pages/ClientUnits/Show.vue

resources/js/pages/Areas/Create.vue
resources/js/pages/Areas/Edit.vue
resources/js/pages/Areas/Show.vue

resources/js/pages/Subareas/Create.vue
resources/js/pages/Subareas/Edit.vue
```

---

## 24.1 Navegação sugerida

```text
Clientes
└── Samarco Mineração
    └── Unidade de Ubu
        └── Usina III
            └── Forno de Endurecimento
```

Usar breadcrumb:

```text
Clientes / Samarco / Ubu / Usina III
```

---

## 24.2 Formulários

Requisitos:

- labels visíveis;
- mensagens de validação;
- botões grandes para celular;
- indicador de processamento;
- bloquear clique duplo;
- confirmação ao sair com alterações;
- máscaras apenas visuais;
- código convertido para maiúsculas;
- status mostrado em badge;
- ativação e inativação com confirmação.

---

## 24.3 Tela de cliente

Deve mostrar:

- nome;
- razão social;
- documento formatado;
- contato;
- status;
- observações;
- unidades cadastradas;
- botão para nova unidade;
- contagem de equipamentos futuramente.

---

# 25. Testes obrigatórios

Criar:

```text
tests/Feature/OperationalStructure/
```

---

## 25.1 Cliente

Testar:

- administrador cria cliente;
- membro não cria cliente;
- cliente recebe organização do usuário;
- `organization_id` enviado pelo frontend é ignorado;
- documento é normalizado;
- documento é único dentro da organização;
- mesmo documento pode existir em outra organização;
- usuário não visualiza cliente de outra organização;
- usuário não edita cliente de outra organização;
- usuário pode inativar cliente da própria organização;
- cliente inativo permanece consultável.

---

## 25.2 Unidade

Testar:

- administrador cria unidade em cliente ativo;
- membro não cria unidade;
- unidade recebe a organização do cliente e tenant;
- código é normalizado;
- código não se repete no mesmo cliente;
- mesmo código pode existir em outro cliente;
- não cria unidade em cliente inativo;
- não cria unidade em cliente de outra organização;
- chave estrangeira composta bloqueia vínculo cruzado;
- usuário não acessa unidade de outra organização.

---

## 25.3 Área

Testar:

- cria área em unidade operacionalmente ativa;
- não cria área em unidade inativa;
- não cria área quando o cliente pai está inativo;
- código único por unidade;
- mesmo código permitido em outra unidade;
- isolamento entre organizações.

---

## 25.4 Subárea

Testar:

- cria subárea em área operacionalmente ativa;
- não cria em área inativa;
- não cria quando algum pai está inativo;
- código único por área;
- mesmo código permitido em outra área;
- isolamento entre organizações.

---

## 25.5 Teste direto da constraint

Criar pelo menos um teste tentando inserir manualmente:

```text
organization_id da Organização A
client_id da Organização B
```

O banco deve rejeitar.

Esse teste confirma que a proteção não depende apenas da aplicação.

---

# 26. Exemplo de teste de isolamento

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\OperationalStructure;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_client_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $userA = User::factory()
            ->for($organizationA)
            ->create();

        $clientB = Client::factory()
            ->for($organizationB)
            ->create();

        $response = $this
            ->actingAs($userA)
            ->get(route('clients.show', $clientB));

        $response->assertForbidden();
    }
}
```

### Preferência entre 403 e 404

Para recursos de outra organização, podemos retornar:

```text
404
```

para não revelar a existência do registro.

A política deve ser padronizada no projeto.

Recomendação:

- consultas filtradas por tenant resultam naturalmente em 404;
- tentativa explícita detectada por Policy pode resultar em 403;
- testes devem refletir o comportamento escolhido.

---

# 27. Seed de demonstração

Atualizar `DevelopmentSeeder` para criar:

```text
Cliente: Samarco Mineração S.A.
Unidade: Ubu
Área: Usina III
Subárea: Forno de Endurecimento
```

Usar `firstOrCreate()` com escopo da organização.

Não criar duplicatas ao executar o seeder novamente.

---

# 28. Validação manual

Após implementar:

1. Entrar como administrador interno.
2. Cadastrar um cliente.
3. Cadastrar uma unidade.
4. Cadastrar uma área.
5. Cadastrar uma subárea.
6. Inativar uma área.
7. Confirmar que ela continua visível.
8. Confirmar que não aceita nova subárea.
9. Entrar como membro.
10. Confirmar que visualiza, mas não edita.
11. Criar uma segunda organização nos testes.
12. Confirmar o isolamento.

---

# 29. Comandos finais

```bash
php artisan migrate
vendor/bin/pint --dirty
php artisan test
npm run build
php artisan route:list
```

Caso ainda não existam dados importantes e seja necessário validar todas as constraints desde o início:

```bash
php artisan migrate:fresh --seed
```

---

# 30. Critérios de aceite

- [ ] `clients` criada.
- [ ] `client_units` criada.
- [ ] `areas` criada.
- [ ] `subareas` criada.
- [ ] todas possuem `organization_id`.
- [ ] todas possuem `public_id`.
- [ ] chaves estrangeiras compostas funcionam.
- [ ] documento é normalizado.
- [ ] códigos são normalizados.
- [ ] unicidade respeita o escopo correto.
- [ ] administrador interno gerencia os cadastros.
- [ ] membro possui apenas consulta.
- [ ] registros inativos continuam no histórico.
- [ ] pai inativo bloqueia novos descendentes.
- [ ] nenhuma exclusão definitiva aparece na interface.
- [ ] consultas são paginadas.
- [ ] páginas são responsivas.
- [ ] usuário não acessa dados de outra organização.
- [ ] testes passam.
- [ ] build passa.
- [ ] documentação corresponde ao código.

---

# 31. Riscos e brechas

## 31.1 Duplicar cliente pelo nome

Nomes podem variar:

```text
Samarco
Samarco Mineração
Samarco Mineração S.A.
```

Mitigação:

- usar documento quando disponível;
- alertar possíveis duplicatas;
- não tornar nome único.

---

## 31.2 Código vazio

Um código vazio convertido incorretamente para `''` pode afetar unicidade.

Mitigação:

- converter vazio para `null`;
- preencher `code` e `normalized_code` juntos.

---

## 31.3 Mudar o pai pela edição

Mover uma área entre unidades pode alterar o significado histórico.

Mitigação:

- não permitir troca do pai no formulário comum;
- futura transferência exigirá Action específica e auditoria.

---

## 31.4 Inativação em cascata

Alterar automaticamente o status de todos os filhos pode apagar a situação original e dificultar reativação.

Mitigação:

- não alterar filhos;
- calcular disponibilidade operacional pela cadeia de pais.

---

## 31.5 Apenas validação na aplicação

Uma falha em Action, Job ou importação poderia criar vínculo cruzado.

Mitigação:

- constraints compostas no MySQL;
- testes diretos de integridade.

---

## 31.6 Carregar a árvore completa

Clientes grandes podem possuir muitas unidades, áreas e subáreas.

Mitigação:

- paginação;
- carregamento por nível;
- contagens;
- eager loading controlado.

---

## 31.7 Soft delete e unicidade

Um registro soft-deleted continua ocupando seu código no índice único.

Isso é intencional neste MVP.

O código antigo não deve ser reutilizado silenciosamente.

---

# 32. Checklist de execução

## 32.1 Resultado da validação

**Estado geral: Em validação.** A implementação planejada foi localizada e os testes automatizados, o Pint, as migrations com seed em SQLite e MySQL 8.4.11 e o build do frontend passaram. O seed foi repetido sem duplicar registros. Os Form Requests agora autorizam antes da validação e as listagens ocultam ações de escrita para membros. O smoke HTTP confirmou os perfis básicos de acesso; permanece obrigatória a conferência visual e responsiva do fluxo completo.

| Item conferido | Evidência encontrada | Resultado |
|---|---|---|
| Enum, normalização e vínculo ao tenant | `RegistrationStatus`, `TextNormalizer` e trait `BelongsToOrganization` | Implementado estaticamente |
| Tabelas e ordem de criação | `000003` clientes, `000004` unidades, `000005` áreas e `000006` subáreas, nessa ordem | Executado em SQLite e MySQL 8.4.11 |
| Integridade e unicidade | FKs compostas impedem pais de outro tenant; índices únicos cobrem documento e códigos normalizados por escopo | Testes passaram em SQLite e MySQL 8.4.11 |
| Models e relacionamentos | `Client`, `ClientUnit`, `Area` e `Subarea`, além dos relacionamentos em `Organization` | Implementado estaticamente |
| Factories e seeder | Factories dos quatro níveis e hierarquia no `DevelopmentSeeder` | Executado duas vezes sem duplicação no MySQL 8.4.11 |
| Actions | Actions de criar, atualizar e alterar status para os quatro níveis | Implementado estaticamente |
| Form Requests | Requests de criação/edição por nível e request compartilhado de status autorizam antes da validação | Implementado e testado |
| Policies | Policies dos quatro recursos restringem escrita ao administrador interno e conferem organização | Testes passaram |
| Controllers e resolução da árvore | Quatro controllers e concern `ResolvesTenantStructure` | Implementado estaticamente |
| Rotas | Resources aninhados com scoped bindings, rotas rasas e alteração de status | Implementado estaticamente |
| Páginas Vue | Páginas de criar, editar e exibir por nível, com ações de escrita ocultas para membros | Build e smoke HTTP passaram; validação visual/responsiva pendente |
| CRUD e autorização de clientes | `ClientCrudTest` cobre criação, normalização, autorização anterior à validação, página Inertia, isolamento e inativação | Testes passaram |
| Hierarquia e pais inativos | `HierarchyCrudTest` cobre criação dos três níveis filhos, autorização e bloqueio por pai inativo | Testes passaram |
| Constraints, escopos e unicidade | `OperationalStructureTest` cobre árvore, escopo do tenant, unicidades e vínculo cruzado | Testes passaram em SQLite e MySQL 8.4.11 |
| Cobertura específica por recurso | Clientes têm arquivo próprio; unidade, área e subárea estão agrupadas em `HierarchyCrudTest` e `OperationalStructureTest` | Testes passaram |
| Validação manual | Login pelo Herd; administrador acessou criação, membro ficou somente com leitura e superadministrador foi bloqueado nas rotas de tenant | **Parcial; CRUD completo e responsividade pendentes** |

## 32.2 Itens de execução

- [x] Criar enum de status.
- [x] Criar normalizador.
- [x] Criar trait de organização.
- [x] Criar models e migrations.
- [x] Revisar ordem das migrations por conferência estática.
- [x] Executar migrations e seed em SQLite.
- [x] Executar migrations, rollback e seed idempotente em MySQL 8.
- [x] Criar relacionamentos.
- [x] Atualizar `Organization`.
- [x] Criar factories.
- [x] Criar Actions.
- [x] Criar Form Requests.
- [x] Criar Policies.
- [x] Criar Controllers.
- [x] Criar rotas.
- [x] Criar páginas Vue.
- [x] Criar testes de cliente.
- [x] Criar testes de unidade.
- [x] Criar testes de área.
- [x] Criar testes de subárea.
- [x] Criar teste de constraint cruzada.
- [x] Atualizar seeder.
- [x] Executar Pint.
- [x] Executar testes.
- [x] Executar build.
- [ ] Validar manualmente.
- [x] Atualizar roadmap.
- [ ] Criar commit.

---

# 33. Commit sugerido

```bash
git add .
git commit -m "feat: add clients and operational hierarchy"
```

---

# 34. Próximo documento

O resultado da validação foi registrado acima. O documento 05 permanece bloqueado pela regra formal do roadmap até a validação visual/responsiva do fluxo completo, o registro do commit e a passagem do pipeline remoto. Depois dessas evidências, o próximo documento será `05-EQUIPAMENTOS-E-DOCUMENTOS.md`.

O próximo documento definirá:

- equipamentos;
- TAG normalizado;
- TAG único por unidade;
- vínculo opcional com subárea;
- documentos e desenhos;
- histórico cadastral;
- status;
- snapshots futuros;
- telas;
- policies;
- testes.
