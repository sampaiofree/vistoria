# 03 — Modelagem Multiempresa

## 1. Objetivo

Implementar a base multiempresa do sistema.

Esta etapa deve garantir que:

- cada organização possua seus próprios usuários;
- cada usuário comum pertença a uma única organização;
- o superadministrador possa existir sem vínculo com uma organização;
- usuários inativos ou suspensos não consigam acessar o sistema;
- organizações suspensas não consigam operar;
- nenhum usuário consiga acessar dados de outra organização;
- o contexto da organização funcione também em Jobs, comandos e testes.

---

## 2. Escopo desta etapa

Será criado:

- tabela `organizations`;
- vínculo de `users` com `organizations`;
- enums de organização e usuário;
- model `Organization`;
- atualização do model `User`;
- `TenantContext`;
- middleware de usuário ativo;
- middleware de organização ativa;
- middleware de resolução do tenant;
- seed de desenvolvimento;
- testes iniciais de isolamento;
- factories;
- commit da fundação multiempresa.

---

## 3. Fora do escopo desta etapa

Não será criado agora:

- clientes;
- unidades;
- áreas;
- subáreas;
- equipamentos;
- inspeções;
- permissões técnicas por inspeção;
- cobrança por organização;
- planos;
- limites de armazenamento;
- domínio personalizado;
- subdomínio por organização;
- painel completo do superadministrador;
- interface de cadastro de organizações.

---

## 4. Decisões aprovadas

### 4.1 Usuário pertence a uma única organização

```text
users.organization_id
```

Não será criada tabela pivot entre usuários e organizações.

### 4.2 Superadministrador

O superadministrador poderá possuir:

```text
organization_id = null
```

### 4.3 Tipos globais de conta

```text
super_admin
company_admin
member
```

### 4.4 Responsabilidades técnicas

Não serão armazenadas diretamente no usuário.

Funções como:

```text
inspector
reviewer
approver
releaser
```

serão atribuídas posteriormente por inspeção.

### 4.5 E-mail

O e-mail será único em todo o sistema.

### 4.6 Exclusão

Organizações e usuários com histórico não deverão ser apagados fisicamente pela interface.

Serão usados estados como:

```text
active
inactive
suspended
```

---

## 5. Estrutura de arquivos

Ao final desta etapa, teremos aproximadamente:

```text
app/
├── Enums/
│   ├── OrganizationStatus.php
│   ├── UserAccountType.php
│   └── UserStatus.php
├── Http/
│   └── Middleware/
│       ├── EnsureOrganizationIsActive.php
│       ├── EnsureUserIsActive.php
│       └── ResolveTenant.php
├── Models/
│   ├── Organization.php
│   └── User.php
├── Services/
│   └── Tenancy/
│       └── TenantContext.php
└── Providers/
    └── AppServiceProvider.php

database/
├── factories/
│   ├── OrganizationFactory.php
│   └── UserFactory.php
├── migrations/
│   ├── xxxx_create_organizations_table.php
│   └── xxxx_add_organization_fields_to_users_table.php
└── seeders/
    ├── DatabaseSeeder.php
    └── DevelopmentSeeder.php

tests/
├── Feature/
│   └── Tenancy/
│       ├── TenantResolutionTest.php
│       ├── UserStatusAccessTest.php
│       └── OrganizationStatusAccessTest.php
└── Unit/
    └── Services/
        └── TenantContextTest.php
```

---

## 6. Ordem de execução

### Passo 1

Criar migrations, model e factory da organização.

### Passo 2

Criar enums.

### Passo 3

Adicionar campos multiempresa aos usuários.

### Passo 4

Atualizar os models e factories.

### Passo 5

Criar o `TenantContext`.

### Passo 6

Criar middlewares.

### Passo 7

Registrar middlewares e serviço.

### Passo 8

Criar seed de desenvolvimento.

### Passo 9

Criar testes.

### Passo 10

Executar migrations, formatação, testes e commit.

---

# 7. Passo 1 — Criar a organização

## 7.1 Comando

```bash
php artisan make:model Organization -mf
```

Esse comando deverá criar:

```text
app/Models/Organization.php
database/factories/OrganizationFactory.php
database/migrations/xxxx_xx_xx_xxxxxx_create_organizations_table.php
```

---

## 7.2 Migration `organizations`

Editar a migration criada:

```php
<?php

declare(strict_types=1);

use App\Enums\OrganizationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();

            $table->string('document', 20)
                ->nullable()
                ->unique();

            $table->string('timezone', 64)
                ->default('America/Sao_Paulo');

            $table->string('status', 20)
                ->default(OrganizationStatus::Active->value);

            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
```

---

## 7.3 Observações

### Documento

O campo `document` deve armazenar somente números.

Exemplo:

```text
21798932000100
```

Não armazenar:

```text
21.798.932/0001-00
```

A máscara pertence à interface.

### `public_id`

Será usado em rotas e URLs.

Exemplo:

```text
/organizations/01K1...
```

O `id` numérico continua sendo usado internamente.

### Suspensão

Os campos:

```text
suspended_at
suspension_reason
```

preservam o motivo e a data da suspensão.

---

# 8. Passo 2 — Criar enums

Criar a pasta, caso ainda não exista:

```bash
mkdir app\Enums
```

No Linux:

```bash
mkdir -p app/Enums
```

---

## 8.1 `OrganizationStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
```

---

## 8.2 `UserAccountType.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum UserAccountType: string
{
    case SuperAdmin = 'super_admin';
    case CompanyAdmin = 'company_admin';
    case Member = 'member';
}
```

---

## 8.3 `UserStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
```

---

# 9. Passo 3 — Alterar usuários

## 9.1 Comando

```bash
php artisan make:migration add_organization_fields_to_users_table --table=users
```

---

## 9.2 Migration

```php
<?php

declare(strict_types=1);

use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();

            $table->ulid('public_id')
                ->unique()
                ->after('organization_id');

            $table->string('account_type', 30)
                ->default(UserAccountType::Member->value)
                ->after('password');

            $table->string('status', 20)
                ->default(UserStatus::Active->value)
                ->after('account_type');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('status');

            $table->timestamp('suspended_at')
                ->nullable()
                ->after('last_login_at');

            $table->text('suspension_reason')
                ->nullable()
                ->after('suspended_at');

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'account_type']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['organization_id', 'account_type']);

            $table->dropConstrainedForeignId('organization_id');

            $table->dropColumn([
                'public_id',
                'account_type',
                'status',
                'last_login_at',
                'suspended_at',
                'suspension_reason',
            ]);
        });
    }
};
```

---

## 9.3 Regra importante

O banco permitirá:

```text
organization_id = null
```

Mas a aplicação só aceitará isso para:

```text
super_admin
```

Essa regra não será deixada apenas na interface.

Será validada nas Actions e testes.

---

# 10. Passo 4 — Model `Organization`

Editar:

```text
app/Models/Organization.php
```

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'document',
        'timezone',
        'status',
        'suspended_at',
        'suspension_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            $organization->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === OrganizationStatus::Suspended;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

# 11. Passo 5 — Atualizar model `User`

Preservar os traits e interfaces existentes no projeto.

Ajustar o conteúdo para incluir:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use Notifiable;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'account_type',
        'status',
        'last_login_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->public_id ??= (string) Str::ulid();
            $user->email = mb_strtolower(trim($user->email));
        });

        static::updating(function (User $user): void {
            if ($user->isDirty('email')) {
                $user->email = mb_strtolower(trim($user->email));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => UserAccountType::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->account_type === UserAccountType::SuperAdmin;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->account_type === UserAccountType::CompanyAdmin;
    }

    public function isMember(): bool
    {
        return $this->account_type === UserAccountType::Member;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function belongsToOrganization(int $organizationId): bool
    {
        return $this->organization_id === $organizationId;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

---

# 12. Passo 6 — Factories

## 12.1 `OrganizationFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company().' LTDA',
            'document' => fake()->unique()->numerify('##############'),
            'timezone' => 'America/Sao_Paulo',
            'status' => OrganizationStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'Suspensa para teste.',
        ]);
    }
}
```

---

## 12.2 Atualizar `UserFactory.php`

Preservar os campos existentes e incluir:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
final class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'account_type' => UserAccountType::Member,
            'status' => UserStatus::Active,
            'last_login_at' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => [
            'organization_id' => null,
            'account_type' => UserAccountType::SuperAdmin,
        ]);
    }

    public function companyAdmin(): static
    {
        return $this->state(fn (): array => [
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Inactive,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'Suspenso para teste.',
        ]);
    }
}
```

---

# 13. Passo 7 — Criar `TenantContext`

Criar:

```text
app/Services/Tenancy/TenantContext.php
```

```php
<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Organization;
use LogicException;

final class TenantContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    public function hasTenant(): bool
    {
        return $this->organization !== null;
    }

    public function organization(): Organization
    {
        return $this->organization
            ?? throw new LogicException('Nenhuma organização foi definida no contexto atual.');
    }

    public function id(): int
    {
        return $this->organization()->getKey();
    }
}
```

---

## 13.1 Registrar como scoped

Editar:

```text
app/Providers/AppServiceProvider.php
```

No método `register()`:

```php
use App\Services\Tenancy\TenantContext;

public function register(): void
{
    $this->app->scoped(TenantContext::class, function (): TenantContext {
        return new TenantContext();
    });
}
```

### Motivo do `scoped`

O contexto deve ser isolado por:

- requisição HTTP;
- execução de Job;
- ciclo do worker.

Não usar `singleton`, pois workers de fila podem reutilizar o processo.

---

# 14. Passo 8 — Middleware `EnsureUserIsActive`

## 14.1 Comando

```bash
php artisan make:middleware EnsureUserIsActive
```

## 14.2 Código

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        if (! $user->isActive()) {
            auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Seu acesso está inativo ou suspenso.',
                ]);
        }

        return $next($request);
    }
}
```

---

# 15. Passo 9 — Middleware `EnsureOrganizationIsActive`

## 15.1 Comando

```bash
php artisan make:middleware EnsureOrganizationIsActive
```

## 15.2 Código

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureOrganizationIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $organization = $user->organization;

        if ($organization === null || ! $organization->isActive()) {
            auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'A organização está inativa ou suspensa.',
                ]);
        }

        return $next($request);
    }
}
```

---

# 16. Passo 10 — Middleware `ResolveTenant`

## 16.1 Comando

```bash
php artisan make:middleware ResolveTenant
```

## 16.2 Código

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        if ($user->isSuperAdmin()) {
            $this->tenantContext->clear();

            return $next($request);
        }

        $organization = $user->organization;

        abort_if($organization === null, 403, 'Usuário sem organização vinculada.');

        $this->tenantContext->set($organization);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
```

---

# 17. Passo 11 — Registrar middlewares

No Laravel 13, aliases e grupos são configurados em:

```text
bootstrap/app.php
```

Adicionar os imports:

```php
use App\Http\Middleware\EnsureOrganizationIsActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Configuration\Middleware;
```

Na configuração de middleware:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'user.active' => EnsureUserIsActive::class,
        'organization.active' => EnsureOrganizationIsActive::class,
        'tenant' => ResolveTenant::class,
    ]);
})
```

Preservar quaisquer configurações já existentes.

---

# 18. Passo 12 — Aplicar middlewares nas rotas

Nas rotas operacionais:

```php
Route::middleware([
    'auth',
    'verified',
    'user.active',
    'organization.active',
    'tenant',
])->group(function (): void {
    // futuras rotas da organização
});
```

Rotas do superadministrador devem ficar em um grupo separado.

Não misturar rotas da plataforma com rotas da organização.

---

# 19. Passo 13 — Seed de desenvolvimento

## 19.1 Criar seeder

```bash
php artisan make:seeder DevelopmentSeeder
```

## 19.2 Código

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrganizationStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['document' => '21798932000100'],
            [
                'name' => 'Empresa de Inspeção',
                'legal_name' => 'Empresa de Inspeção LTDA',
                'timezone' => 'America/Sao_Paulo',
                'status' => OrganizationStatus::Active,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@vistoria.test'],
            [
                'organization_id' => $organization->id,
                'name' => 'Administrador da Empresa',
                'password' => Hash::make('password'),
                'account_type' => UserAccountType::CompanyAdmin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'superadmin@vistoria.test'],
            [
                'organization_id' => null,
                'name' => 'Superadministrador',
                'password' => Hash::make('password'),
                'account_type' => UserAccountType::SuperAdmin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
    }
}
```

---

## 19.3 Chamar somente em ambiente local

Em `DatabaseSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
```

---

# 20. Passo 14 — Teste unitário do `TenantContext`

## 20.1 Comando

```bash
php artisan make:test Unit/Services/TenantContextTest --unit
```

## 20.2 Código

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Services\Tenancy\TenantContext;
use LogicException;
use PHPUnit\Framework\TestCase;

final class TenantContextTest extends TestCase
{
    public function test_it_stores_the_current_organization(): void
    {
        $organization = new Organization();
        $organization->setAttribute('id', 10);

        $context = new TenantContext();
        $context->set($organization);

        $this->assertTrue($context->hasTenant());
        $this->assertSame(10, $context->id());
        $this->assertSame($organization, $context->organization());
    }

    public function test_it_throws_when_no_tenant_is_defined(): void
    {
        $context = new TenantContext();

        $this->expectException(LogicException::class);

        $context->id();
    }

    public function test_it_can_clear_the_context(): void
    {
        $organization = new Organization();
        $organization->setAttribute('id', 10);

        $context = new TenantContext();
        $context->set($organization);
        $context->clear();

        $this->assertFalse($context->hasTenant());
    }
}
```

---

# 21. Passo 15 — Testes de acesso

## 21.1 Usuário inativo

Criar:

```bash
php artisan make:test Feature/Tenancy/UserStatusAccessTest
```

Exemplo:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserStatusAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_access_protected_routes(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
```

---

## 21.2 Organização suspensa

Criar:

```bash
php artisan make:test Feature/Tenancy/OrganizationStatusAccessTest
```

Exemplo:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationStatusAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_from_suspended_organization_cannot_access_the_dashboard(): void
    {
        $organization = Organization::factory()
            ->suspended()
            ->create();

        $user = User::factory()
            ->for($organization)
            ->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
```

---

## 21.3 Resolução do tenant

Criar:

```bash
php artisan make:test Feature/Tenancy/TenantResolutionTest
```

Exemplo:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            'web',
            'auth',
            'user.active',
            'organization.active',
            'tenant',
        ])->get('/_test/tenant', function (TenantContext $tenant): array {
            return [
                'organization_id' => $tenant->id(),
            ];
        });
    }

    public function test_it_resolves_the_authenticated_users_organization(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()
            ->for($organization)
            ->create();

        $response = $this
            ->actingAs($user)
            ->get('/_test/tenant');

        $response
            ->assertOk()
            ->assertJson([
                'organization_id' => $organization->id,
            ]);
    }
}
```

---

# 22. Passo 16 — Regra de integridade do usuário

A aplicação deverá impedir:

```text
member com organization_id nulo
company_admin com organization_id nulo
super_admin com organization_id preenchido
```

Essa validação será aplicada nas futuras Actions de criação e atualização de usuários.

Nesta etapa, criar ao menos testes documentando a regra.

Exemplo conceitual:

```text
UserAccountType::SuperAdmin
→ organization_id deve ser null

UserAccountType::CompanyAdmin
→ organization_id obrigatório

UserAccountType::Member
→ organization_id obrigatório
```

Não adicionar automaticamente um observer que altere dados silenciosamente.

---

# 23. Passo 17 — Executar

Como o projeto ainda está no início e não possui dados relevantes:

```bash
php artisan migrate:fresh --seed
```

Depois:

```bash
vendor/bin/pint --dirty
php artisan test
npm run build
```

---

# 24. Validação manual

## 24.1 Banco

Confirmar:

```text
organizations
users.organization_id
users.public_id
users.account_type
users.status
```

## 24.2 Organização inicial

Confirmar:

```text
Empresa de Inspeção
status: active
timezone: America/Sao_Paulo
```

## 24.3 Usuário administrador

Confirmar:

```text
admin@vistoria.test
account_type: company_admin
organization_id preenchido
```

## 24.4 Superadministrador

Confirmar:

```text
superadmin@vistoria.test
account_type: super_admin
organization_id nulo
```

## 24.5 Login

Confirmar que:

- usuário ativo entra;
- usuário inativo é desconectado;
- usuário suspenso é desconectado;
- usuário de organização suspensa é desconectado.

---

# 25. Critérios de aceite

## 25.1 Resultado da validação

**Estado geral: Em validação.** A implementação prevista para a fundação multiempresa foi reconciliada com este documento. As migrations, o rollback, o seed idempotente e a suíte automatizada foram executados com SQLite e MySQL 8.4.11; a suíte também passou no MariaDB 12.2 usado pelo ambiente local. O Pint passou, o frontend compilou com Node 22 e o smoke HTTP confirmou os perfis básicos de acesso. Permanecem obrigatórias a validação visual dos fluxos completos e o registro do commit.

| Critério | Implementação encontrada | Validação | Resultado da conferência |
|---|---|---|---|
| Migration `organizations` executa sem erro | Migration `000001` cria `public_id`, timezone, status, suspensão, timestamps, índices e soft delete | Executada em SQLite e MySQL 8.4.11 | Validado no banco alvo |
| Usuários possuem vínculo opcional com organização | Migration `000002` adiciona FK anulável com exclusão restrita e o model define o relacionamento | Executada e revertida em SQLite e MySQL 8.4.11 | Validado no banco alvo |
| Organização possui `public_id` | Migration, model com `HasPublicId`, factory, seed e testes foram ajustados | Teste automatizado passou nos dois bancos | Implementado e validado |
| Usuário possui `public_id` | Migration, model com `HasPublicId`, factory, seed e testes foram ajustados | Teste automatizado passou nos dois bancos | Implementado e validado |
| Enums funcionam nos casts | `Organization` e `User` declaram casts; organização possui estados ativo, suspenso e inativo | Testes passaram | Implementado e validado |
| Superadministrador pode existir sem organização | `User::booted()` permite esse caso e impede tenant | Testes passaram | Implementado e validado |
| Administrador interno possui organização | `User::booted()` rejeita contas não super-admin sem organização | Testes passaram | Implementado e validado |
| Usuário inativo não acessa rotas protegidas | `EnsureUserIsActive` encerra a sessão | Teste passou; validação manual pendente | Em validação |
| Organização suspensa não opera | `EnsureOrganizationIsActive` encerra a sessão e os metadados de suspensão são preservados | Testes passaram; validação manual pendente | Em validação |
| `TenantContext` resolve a organização | Classe, binding `scoped`, middleware e testes existem | Testes passaram | Implementado e validado |
| `TenantContext` é limpo ao fim da requisição | `ResolveTenant` limpa o contexto em `finally` | Testes passaram | Implementado e validado |
| Seed local funciona | `DevelopmentSeeder` preserva `public_id` mesmo com eventos de model desabilitados | Seed executado duas vezes sem duplicar registros no MySQL 8.4.11 | Validado no banco alvo |
| Factories funcionam | Factories cobrem estados e metadados previstos | Testes passaram | Implementado e validado |
| Policies iniciais existem | Não se aplicam nesta etapa: o módulo 03 não expõe CRUD de organizações ou usuários; recursos operacionais possuem policies próprias | Decisão registrada | Não se aplica |
| Testes passam | Suíte completa executada com 47 testes aprovados e 1 teste de concorrência do módulo 06 ignorado | Comando terminou com sucesso em SQLite, MariaDB 12.2 e MySQL 8.4.11 | Validado no banco alvo |
| Build do frontend passa | Dependência nativa específica de Windows foi removida e a versão de Node foi fixada | Build com Node 22 passou | Validado |
| Documentação reflete o código real | Campos, estados, policies e evidências foram reconciliados | Validação visual completa e commit ainda pendentes | Parcial |

---

# 26. Riscos e brechas

## 26.1 Confiar apenas em middleware

O middleware resolve o contexto, mas não substitui:

- policies;
- filtros nas queries;
- constraints no banco;
- testes de isolamento.

## 26.2 Superadministrador vazando para operações comuns

O superadministrador sem tenant não deve executar Actions operacionais sem escolher explicitamente uma organização.

## 26.3 `singleton` em workers

Usar `singleton` para TenantContext pode carregar o tenant anterior em outro Job.

Por isso foi definido:

```php
$this->app->scoped(...)
```

## 26.4 Seed executado em produção

Credenciais previsíveis não podem existir em produção.

Por isso o seeder de desenvolvimento deve ser condicionado ao ambiente.

## 26.5 Exclusão da organização

Não usar exclusão em cascata.

Uma organização deve ser suspensa ou inativada.

## 26.6 `organization_id` vindo do frontend

O frontend não deve definir o tenant de registros operacionais.

O backend deve usar o TenantContext.

---

# 27. Checklist de execução

- [x] Criar model, factory e migration de organização.
- [x] Criar enums.
- [x] Criar migration dos usuários.
- [x] Atualizar `Organization`.
- [x] Atualizar `User`.
- [x] Atualizar factories.
- [x] Criar `TenantContext`.
- [x] Registrar `TenantContext` como scoped.
- [x] Criar middleware de usuário ativo.
- [x] Criar middleware de organização ativa.
- [x] Criar middleware de tenant.
- [x] Registrar aliases.
- [x] Aplicar middleware às rotas.
- [x] Criar seeder local.
- [x] Criar testes unitários.
- [x] Criar testes de acesso.
- [x] Adicionar `public_id` a organizações e usuários e ajustar models/factories/testes.
- [x] Registrar formalmente que policies de organização e usuário não se aplicam a este módulo sem CRUD correspondente.
- [x] Executar migrations e seed em SQLite.
- [x] Executar migrations, rollback e seed idempotente em MySQL 8.
- [x] Executar Pint.
- [x] Executar testes.
- [x] Executar build.
- [ ] Validar manualmente.
- [x] Atualizar o status no roadmap.
- [ ] Criar commit.

---

# 28. Commit sugerido

```bash
git add .
git commit -m "feat: implement multi-tenant organization foundation"
```

---

# 29. Próximo documento

O resultado da validação foi registrado acima. O documento 03 permanece **Em validação** até a conferência visual dos fluxos completos e o registro do commit.

O próximo documento definirá:

- clientes atendidos;
- unidades dos clientes;
- áreas;
- subáreas;
- relacionamentos;
- unicidade por organização;
- telas de cadastro;
- policies;
- testes de isolamento.
