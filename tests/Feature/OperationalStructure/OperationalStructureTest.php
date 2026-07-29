<?php

namespace Tests\Feature\OperationalStructure;

use App\Enums\RegistrationStatus;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Organization;
use App\Models\Subarea;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_hierarchy_and_keeps_the_chain_active(): void
    {
        $organization = Organization::factory()->create();

        $client = Client::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unit = ClientUnit::factory()
            ->forClient($client)
            ->create();

        $area = Area::factory()
            ->forUnit($unit)
            ->create();

        $subarea = Subarea::factory()
            ->forArea($area)
            ->create();

        $this->assertNotEmpty($client->public_id);
        $this->assertNotEmpty($unit->public_id);
        $this->assertNotEmpty($area->public_id);
        $this->assertNotEmpty($subarea->public_id);

        $this->assertTrue($client->isActive());
        $this->assertTrue($unit->isOperationallyActive());
        $this->assertTrue($area->isOperationallyActive());
        $this->assertTrue($subarea->isOperationallyActive());

        $this->assertSame($organization->id, $client->organization_id);
        $this->assertSame($client->id, $unit->client_id);
        $this->assertSame($unit->id, $area->client_unit_id);
        $this->assertSame($area->id, $subarea->area_id);
    }

    public function test_scope_for_organization_filters_clients(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        Client::factory()->count(2)->create([
            'organization_id' => $organizationA->id,
        ]);

        Client::factory()->create([
            'organization_id' => $organizationB->id,
        ]);

        $this->assertCount(
            2,
            Client::query()->forOrganization($organizationA->id)->get(),
        );

        $this->assertCount(
            1,
            Client::query()->forOrganization($organizationB->id)->get(),
        );
    }

    public function test_client_document_is_unique_within_the_same_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        Client::factory()->create([
            'organization_id' => $organizationA->id,
            'document' => '11222333000144',
        ]);

        Client::factory()->create([
            'organization_id' => $organizationB->id,
            'document' => '11222333000144',
        ]);

        $this->expectException(QueryException::class);

        Client::factory()->create([
            'organization_id' => $organizationA->id,
            'document' => '11222333000144',
        ]);
    }

    public function test_unit_code_is_unique_within_the_same_client(): void
    {
        $organization = Organization::factory()->create();

        $clientA = Client::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $clientB = Client::factory()->create([
            'organization_id' => $organization->id,
        ]);

        ClientUnit::factory()
            ->forClient($clientA)
            ->create([
                'code' => 'UN-001',
                'normalized_code' => 'UN-001',
            ]);

        ClientUnit::factory()
            ->forClient($clientB)
            ->create([
                'code' => 'UN-001',
                'normalized_code' => 'UN-001',
            ]);

        $this->expectException(QueryException::class);

        ClientUnit::factory()
            ->forClient($clientA)
            ->create([
                'code' => 'UN-001',
                'normalized_code' => 'UN-001',
            ]);
    }

    public function test_area_and_subarea_codes_are_unique_within_the_parent(): void
    {
        $organization = Organization::factory()->create();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unitA = ClientUnit::factory()->forClient($client)->create();
        $unitB = ClientUnit::factory()->forClient($client)->create();

        Area::factory()
            ->forUnit($unitA)
            ->create([
                'code' => 'AR-001',
                'normalized_code' => 'AR-001',
            ]);

        Area::factory()
            ->forUnit($unitB)
            ->create([
                'code' => 'AR-001',
                'normalized_code' => 'AR-001',
            ]);

        $this->expectException(QueryException::class);

        Area::factory()
            ->forUnit($unitA)
            ->create([
                'code' => 'AR-001',
                'normalized_code' => 'AR-001',
            ]);
    }

    public function test_cross_tenant_links_are_rejected_by_the_database(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $clientB = Client::factory()->create([
            'organization_id' => $organizationB->id,
        ]);

        $this->expectException(QueryException::class);

        ClientUnit::query()->create([
            'organization_id' => $organizationA->id,
            'client_id' => $clientB->id,
            'name' => 'Unidade inválida',
            'code' => 'UN-X',
            'normalized_code' => 'UN-X',
            'timezone' => 'America/Sao_Paulo',
            'address_line' => null,
            'address_number' => null,
            'district' => null,
            'postal_code' => null,
            'city' => null,
            'state' => null,
            'country_code' => 'BR',
            'status' => RegistrationStatus::Active->value,
            'notes' => null,
        ]);
    }

    public function test_subarea_codes_are_unique_within_the_same_area(): void
    {
        $organization = Organization::factory()->create();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $unit = ClientUnit::factory()->forClient($client)->create();
        $areaA = Area::factory()->forUnit($unit)->create();
        $areaB = Area::factory()->forUnit($unit)->create();

        Subarea::factory()
            ->forArea($areaA)
            ->create([
                'code' => 'SA-001',
                'normalized_code' => 'SA-001',
            ]);

        Subarea::factory()
            ->forArea($areaB)
            ->create([
                'code' => 'SA-001',
                'normalized_code' => 'SA-001',
            ]);

        $this->expectException(QueryException::class);

        Subarea::factory()
            ->forArea($areaA)
            ->create([
                'code' => 'SA-001',
                'normalized_code' => 'SA-001',
            ]);
    }
}
