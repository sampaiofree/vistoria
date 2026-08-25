<?php

declare(strict_types=1);

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationStatus;
use App\Enums\UserAccountType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GlobalOrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_an_organization_and_its_first_administrator(): void
    {
        $master = $this->master();

        $response = $this->actingAs($master)->post(route('admin.organizations.store'), [
            'name' => '  Empresa Alfa  ',
            'legal_name' => 'Empresa Alfa Ltda',
            'document' => '04.252.011/0001-10',
            'admin_name' => '  Ana Administradora ',
            'admin_email' => 'ANA@ALFA.TEST ',
        ]);

        $response->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHas('temporary_credentials');

        $organization = Organization::query()->where('name', 'Empresa Alfa')->firstOrFail();
        $administrator = User::query()->where('email', 'ana@alfa.test')->firstOrFail();
        $credentials = $response->getSession()->get('temporary_credentials');

        $this->assertSame('Empresa Alfa Ltda', $organization->legal_name);
        $this->assertSame('04252011000110', $organization->document);
        $this->assertSame('America/Sao_Paulo', $organization->timezone);
        $this->assertSame(OrganizationStatus::Active, $organization->status);
        $this->assertSame($organization->id, $administrator->organization_id);
        $this->assertSame(UserAccountType::CompanyAdmin, $administrator->account_type);
        $this->assertTrue($administrator->must_change_password);
        $this->assertTrue($administrator->isActive());
        $this->assertIsArray($credentials);
        $this->assertTrue(Hash::check($credentials['password'], $administrator->password));
        $this->assertNotSame($credentials['password'], $administrator->password);
        $categories = $organization->defectCategories()
            ->with('gutOptions')
            ->get()
            ->keyBy('code');

        $this->assertSame(['CV', 'REC', 'TAC'], $categories->keys()->sort()->values()->all());
        $this->assertSame(5, $categories['CV']->classifications()->count());
        $this->assertSame(3, $categories['TAC']->classifications()->count());
        $this->assertSame(5, $categories['REC']->classifications()->count());
        $this->assertSame(15, $categories['CV']->gutOptions->count());
        $this->assertSame(15, $categories['TAC']->gutOptions->count());
        $this->assertSame(15, $categories['REC']->gutOptions->count());
    }

    public function test_conflicting_administrator_email_or_cnpj_does_not_create_a_partial_organization(): void
    {
        $master = $this->master();
        $existingOrganization = Organization::factory()->create();
        User::factory()->for($existingOrganization)->create(['email' => 'existente@empresa.test']);

        $this->actingAs($master)
            ->from(route('admin.organizations.create'))
            ->post(route('admin.organizations.store'), [
                'name' => 'Empresa Sem Cadastro',
                'admin_name' => 'Administrador Existente',
                'admin_email' => 'existente@empresa.test',
            ])
            ->assertRedirect(route('admin.organizations.create'))
            ->assertSessionHasErrors('admin_email');

        $this->assertSame(1, Organization::query()->count());

        Organization::factory()->create(['document' => '04252011000110']);

        $this->actingAs($master)
            ->from(route('admin.organizations.create'))
            ->post(route('admin.organizations.store'), [
                'name' => 'Empresa Com CNPJ Repetido',
                'document' => '04.252.011/0001-10',
                'admin_name' => 'Novo Administrador',
                'admin_email' => 'novo@empresa.test',
            ])
            ->assertRedirect(route('admin.organizations.create'))
            ->assertSessionHasErrors('document');

        $this->assertSame(2, Organization::query()->count());
        $this->assertNull(User::query()->where('email', 'novo@empresa.test')->first());
    }

    public function test_only_an_active_super_admin_with_changed_password_can_access_global_organization_management(): void
    {
        $this->get(route('admin.organizations.index'))->assertRedirect(route('login'));

        $companyUser = User::factory()->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $this->actingAs($companyUser)->get(route('admin.organizations.index'))->assertForbidden();

        $pendingMaster = User::factory()->superAdmin()->create(['must_change_password' => true]);
        $this->actingAs($pendingMaster)
            ->get(route('admin.organizations.index'))
            ->assertRedirect(route('account.password.edit'));

        $this->actingAs($this->master())
            ->get(route('admin.organizations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Index')
                ->has('organizations.data', 1));
    }

    public function test_master_can_list_filter_edit_suspend_and_reactivate_organizations(): void
    {
        $master = $this->master();
        $alpha = Organization::factory()->create(['name' => 'Empresa Alfa']);
        $suspended = Organization::factory()->suspended()->create(['name' => 'Empresa Beta']);

        $this->actingAs($master)
            ->get(route('admin.organizations.index', ['search' => 'Alfa', 'status' => OrganizationStatus::Active->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Index')
                ->where('filters.search', 'Alfa')
                ->where('filters.status', OrganizationStatus::Active->value)
                ->has('organizations.data', 1)
                ->where('organizations.data.0.name', 'Empresa Alfa'));

        $this->actingAs($master)
            ->put(route('admin.organizations.update', $alpha), [
                'name' => 'Empresa Alfa Atualizada',
                'legal_name' => 'Alfa Atualizada Ltda',
                'document' => '04.252.011/0001-10',
            ])
            ->assertRedirect(route('admin.organizations.index'));

        $this->assertSame('Empresa Alfa Atualizada', $alpha->refresh()->name);
        $this->assertSame('04252011000110', $alpha->document);

        $this->actingAs($master)
            ->patch(route('admin.organizations.status', $alpha), ['status' => OrganizationStatus::Suspended->value])
            ->assertSessionHas('success', 'Empresa suspensa.');

        $this->assertSame(OrganizationStatus::Suspended, $alpha->refresh()->status);
        $this->assertNotNull($alpha->suspended_at);

        $this->actingAs($master)
            ->patch(route('admin.organizations.status', $suspended), ['status' => OrganizationStatus::Active->value])
            ->assertSessionHas('success', 'Empresa reativada.');

        $this->assertSame(OrganizationStatus::Active, $suspended->refresh()->status);
        $this->assertNull($suspended->suspended_at);
    }

    private function master(): User
    {
        return User::factory()->superAdmin()->create([
            'must_change_password' => false,
        ]);
    }
}
