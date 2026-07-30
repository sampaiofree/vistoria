<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionReferenceDocumentRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_sync_reference_documents_and_keep_specific_revision(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $inspection = Inspection::factory()
            ->create(['organization_id' => $organization->id]);
        $equipment = $inspection->equipment;

        $documentV1 = EquipmentDocument::factory()
            ->forEquipment($equipment)
            ->create([
                'organization_id' => $organization->id,
                'title' => 'Desenho técnico',
                'revision' => 'R01',
            ]);
        $documentV2 = EquipmentDocument::factory()
            ->revisionOf($documentV1)
            ->create([
                'organization_id' => $organization->id,
                'title' => 'Desenho técnico',
                'revision' => 'R02',
            ]);

        $this->actingAs($admin)
            ->put(route('inspections.reference-documents.update', $inspection), [
                'reference_document_ids' => [$documentV1->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inspection_reference_documents', [
            'inspection_id' => $inspection->id,
            'equipment_document_id' => $documentV1->id,
        ]);

        $this->assertSame(
            $documentV1->id,
            InspectionReferenceDocument::query()->firstOrFail()->equipment_document_id,
        );

        $this->actingAs($admin)
            ->put(route('inspections.reference-documents.update', $inspection), [
                'reference_document_ids' => [$documentV2->id],
            ])
            ->assertRedirect();

        $referenceDocument = InspectionReferenceDocument::query()->firstOrFail();

        $this->assertSame($documentV2->id, $referenceDocument->equipment_document_id);
        $this->assertDatabaseMissing('inspection_reference_documents', [
            'inspection_id' => $inspection->id,
            'equipment_document_id' => $documentV1->id,
        ]);
        $this->assertDatabaseHas('inspection_reference_documents', [
            'inspection_id' => $inspection->id,
            'equipment_document_id' => $documentV2->id,
        ]);
    }

    public function test_documents_from_other_equipment_are_blocked(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $inspection = Inspection::factory()
            ->create(['organization_id' => $organization->id]);
        $otherEquipment = Equipment::factory()
            ->for($organization)
            ->create();
        $foreignDocument = EquipmentDocument::factory()
            ->forEquipment($otherEquipment)
            ->create(['organization_id' => $organization->id]);

        $this->actingAs($admin)
            ->put(route('inspections.reference-documents.update', $inspection), [
                'reference_document_ids' => [$foreignDocument->id],
            ])
            ->assertSessionHasErrors('document');
    }

    public function test_reference_documents_can_be_removed_through_real_route(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $inspection = Inspection::factory()
            ->create(['organization_id' => $organization->id]);
        $equipment = $inspection->equipment;
        $document = EquipmentDocument::factory()
            ->forEquipment($equipment)
            ->create(['organization_id' => $organization->id]);

        $this->actingAs($admin)
            ->put(route('inspections.reference-documents.update', $inspection), [
                'reference_document_ids' => [$document->id],
            ])
            ->assertRedirect();

        $referenceDocument = InspectionReferenceDocument::query()->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('inspections.reference-documents.destroy', [$inspection, $referenceDocument]))
            ->assertRedirect();

        $this->assertDatabaseMissing('inspection_reference_documents', [
            'id' => $referenceDocument->id,
        ]);
    }
}
