<?php

namespace Tests\Feature\OperationalStructure;

use App\Enums\UserAccountType;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Organization;
use App\Models\Subarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HierarchyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_unit_area_and_subarea(): void
    {
        $organization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        $client = Client::factory()
            ->for($organization)
            ->create();

        $unitResponse = $this
            ->actingAs($admin)
            ->post(route('clients.units.store', $client), [
                'name' => 'Unidade Piloto',
                'code' => ' u03 ',
                'timezone' => 'America/Sao_Paulo',
                'address_line' => 'Rua A',
                'address_number' => '100',
                'district' => 'Centro',
                'postal_code' => '01000-000',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'country_code' => 'br',
                'notes' => 'Nota da unidade',
            ]);

        $unitResponse->assertRedirect();

        $unit = ClientUnit::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('U03', $unit->code);
        $this->assertSame('U03', $unit->normalized_code);

        $areaResponse = $this
            ->actingAs($admin)
            ->post(route('units.areas.store', $unit), [
                'name' => 'Usina III',
                'code' => ' usina iii ',
                'description' => 'Area principal',
            ]);

        $areaResponse->assertRedirect();

        $area = Area::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('USINAIII', $area->normalized_code);

        $subareaResponse = $this
            ->actingAs($admin)
            ->post(route('areas.subareas.store', $area), [
                'name' => 'Forno de Endurecimento',
                'code' => ' forno de endurecimento ',
                'description' => 'Detalhe da subarea',
            ]);

        $subareaResponse->assertRedirect();

        $subarea = Subarea::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('FORNODEENDURECIMENTO', $subarea->normalized_code);
    }

    public function test_member_cannot_create_nested_resources(): void
    {
        $organization = Organization::factory()->create();

        $member = User::factory()
            ->for($organization)
            ->create();

        $client = Client::factory()
            ->for($organization)
            ->create();

        $unit = ClientUnit::factory()
            ->forClient($client)
            ->create();

        $area = Area::factory()
            ->forUnit($unit)
            ->create();

        $this->actingAs($member)
            ->post(route('clients.units.store', $client), [
                'name' => 'Restrito',
                'timezone' => 'America/Sao_Paulo',
                'country_code' => 'BR',
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('units.areas.store', $unit), [
                'name' => 'Restrito',
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('areas.subareas.store', $area), [
                'name' => 'Restrito',
            ])
            ->assertForbidden();
    }

    public function test_inactive_parents_block_new_children(): void
    {
        $organization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        $client = Client::factory()
            ->for($organization)
            ->create([
                'status' => 'inactive',
            ]);

        $this->actingAs($admin)
            ->post(route('clients.units.store', $client), [
                'name' => 'Unidade bloqueada',
                'code' => 'UB-01',
                'timezone' => 'America/Sao_Paulo',
                'country_code' => 'BR',
            ])
            ->assertSessionHasErrors('client_id');

        $client = Client::factory()
            ->for($organization)
            ->create();

        $unit = ClientUnit::factory()
            ->forClient($client)
            ->inactive()
            ->create();

        $this->actingAs($admin)
            ->post(route('units.areas.store', $unit), [
                'name' => 'Area bloqueada',
                'code' => 'AR-01',
            ])
            ->assertSessionHasErrors('client_unit_id');

        $unit = ClientUnit::factory()
            ->forClient($client)
            ->create();

        $area = Area::factory()
            ->forUnit($unit)
            ->inactive()
            ->create();

        $this->actingAs($admin)
            ->post(route('areas.subareas.store', $area), [
                'name' => 'Subarea bloqueada',
                'code' => 'SA-01',
            ])
            ->assertSessionHasErrors('area_id');
    }
}
