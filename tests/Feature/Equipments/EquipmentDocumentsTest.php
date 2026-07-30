<?php

declare(strict_types=1);

namespace Tests\Feature\Equipments;

use App\Enums\EquipmentDocumentType;
use App\Enums\UserAccountType;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Organization;
use App\Models\Subarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EquipmentDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_document_create_revision_and_toggle_status(): void
    {
        Storage::fake('equipment_documents');

        $organization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        [$client, $unit, $area, $subarea] = $this->createActiveHierarchy($organization);

        $equipment = Equipment::factory()
            ->inStructure($client, $unit, $area, $subarea)
            ->create();

        $firstUpload = $this->actingAs($admin)->post(route('equipments.documents.store', $equipment), [
            'document_type' => EquipmentDocumentType::Manual->value,
            'title' => 'Manual do equipamento',
            'document_number' => 'DOC-01',
            'revision' => 'R01',
            'description' => 'Versao inicial',
            'issued_at' => '2026-07-30',
            'file' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'),
        ]);

        $firstUpload->assertRedirect(route('equipments.show', $equipment));

        $firstDocument = EquipmentDocument::query()->firstOrFail();

        Storage::disk('equipment_documents')->assertExists($firstDocument->path);

        $this->assertSame($organization->id, $firstDocument->organization_id);
        $this->assertSame($equipment->id, $firstDocument->equipment_id);
        $this->assertSame(EquipmentDocumentType::Manual, $firstDocument->document_type);
        $this->assertSame('active', $firstDocument->status->value);
        $this->assertTrue($firstDocument->is_current);
        $this->assertSame(64, strlen($firstDocument->checksum));

        $secondUpload = $this->actingAs($admin)->post(route('equipments.documents.store', $equipment), [
            'document_type' => EquipmentDocumentType::Manual->value,
            'title' => 'Manual do equipamento',
            'document_number' => 'DOC-01',
            'revision' => 'R02',
            'document_group' => $firstDocument->document_group,
            'description' => 'Segunda revisao',
            'issued_at' => '2026-07-30',
            'file' => UploadedFile::fake()->create('manual-r02.pdf', 100, 'application/pdf'),
        ]);

        $secondUpload->assertRedirect(route('equipments.show', $equipment));

        $firstDocument->refresh();
        $currentDocument = EquipmentDocument::query()
            ->where('document_group', $firstDocument->document_group)
            ->where('is_current', true)
            ->firstOrFail();

        $this->assertFalse($firstDocument->is_current);
        $this->assertSame('R02', $currentDocument->revision);
        $this->assertTrue($currentDocument->is_current);

        $this->actingAs($admin)
            ->patch(route('equipment-documents.current', $firstDocument))
            ->assertRedirect();

        $firstDocument->refresh();
        $currentDocument->refresh();

        $this->assertTrue($firstDocument->is_current);
        $this->assertFalse($currentDocument->is_current);

        $this->actingAs($admin)
            ->patch(route('equipment-documents.status', $currentDocument), [
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertSame('inactive', $currentDocument->refresh()->status->value);
    }

    public function test_member_can_view_document_but_cannot_upload_and_other_tenant_gets_404(): void
    {
        Storage::fake('equipment_documents');

        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        $member = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::Member->value,
            ]);

        $otherAdmin = User::factory()
            ->for($otherOrganization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        [$client, $unit, $area, $subarea] = $this->createActiveHierarchy($organization);

        $equipment = Equipment::factory()
            ->inStructure($client, $unit, $area, $subarea)
            ->create();

        $this->actingAs($admin)->post(route('equipments.documents.store', $equipment), [
            'document_type' => EquipmentDocumentType::TechnicalDrawing->value,
            'title' => 'Desenho técnico',
            'revision' => 'R04',
            'file' => UploadedFile::fake()->create('desenho.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $document = EquipmentDocument::query()->firstOrFail();

        $this->actingAs($member)
            ->get(route('equipment-documents.show', $document))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('EquipmentDocuments/Show')
                ->where('can.download', true));

        $this->actingAs($member)
            ->get(route('equipment-documents.download', $document))
            ->assertOk();

        $this->actingAs($member)
            ->post(route('equipments.documents.store', $equipment), [
                'document_type' => EquipmentDocumentType::Manual->value,
                'title' => 'Restrito',
                'file' => UploadedFile::fake()->create('restrito.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($otherAdmin)
            ->get(route('equipment-documents.show', $document))
            ->assertNotFound();

        $this->actingAs($otherAdmin)
            ->get(route('equipment-documents.download', $document))
            ->assertNotFound();
    }

    /**
     * @return array{0: Client, 1: ClientUnit, 2: Area, 3: Subarea}
     */
    private function createActiveHierarchy(Organization $organization): array
    {
        $client = Client::factory()
            ->for($organization)
            ->create();

        $unit = ClientUnit::factory()
            ->forClient($client)
            ->create();

        $area = Area::factory()
            ->forUnit($unit)
            ->create();

        $subarea = Subarea::factory()
            ->forArea($area)
            ->create();

        return [$client, $unit, $area, $subarea];
    }
}
