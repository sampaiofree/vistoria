<?php

namespace Tests\Feature\Inspections;

use App\Actions\Inspections\AttachInspectionReferenceDocument;
use App\Actions\Inspections\DetachInspectionReferenceDocument;
use App\Enums\InspectionStatus;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InspectionReferenceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_attaches_the_concrete_revision_and_server_owned_audit_fields(): void
    {
        [$organization, $actor, $equipment, $inspection] = $this->scenario();
        $oldRevision = EquipmentDocument::factory()->forEquipment($equipment)->create(['revision' => '1', 'is_current' => false]);
        EquipmentDocument::factory()->revisionOf($oldRevision)->create(['revision' => '2']);

        $reference = $this->attach()->handle($actor, $inspection->id, $oldRevision->id);

        $this->assertSame($oldRevision->id, $reference->equipment_document_id);
        $this->assertSame($organization->id, $reference->organization_id);
        $this->assertSame($actor->id, $reference->added_by);
        $this->assertNotNull($reference->created_at);
    }

    public function test_it_rejects_an_inspection_or_document_from_another_tenant(): void
    {
        [, $actor, $equipment, $inspection] = $this->scenario();
        $otherEquipment = Equipment::factory()->create();
        $otherDocument = EquipmentDocument::factory()->forEquipment($otherEquipment)->create();

        $this->expectException(ValidationException::class);
        $this->attach()->handle($actor, $inspection->id, $otherDocument->id);
    }

    public function test_it_rejects_a_document_from_another_equipment(): void
    {
        [$organization, $actor, , $inspection] = $this->scenario();
        $document = EquipmentDocument::factory()->forEquipment(Equipment::factory()->create(['organization_id' => $organization->id]))->create();

        $this->expectException(ValidationException::class);
        $this->attach()->handle($actor, $inspection->id, $document->id);
    }

    public function test_duplicate_attachment_is_a_validation_error(): void
    {
        [, $actor, $equipment, $inspection] = $this->scenario();
        $document = EquipmentDocument::factory()->forEquipment($equipment)->create();
        $this->attach()->handle($actor, $inspection->id, $document->id);

        try {
            $this->attach()->handle($actor, $inspection->id, $document->id);
            $this->fail('A duplicate attachment should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('equipment_document_id', $exception->errors());
        }
    }

    public function test_references_are_immutable_after_technical_closure(): void
    {
        [, $actor, $equipment, $inspection] = $this->scenario();
        $document = EquipmentDocument::factory()->forEquipment($equipment)->create();
        $this->attach()->handle($actor, $inspection->id, $document->id);
        $inspection->update(['status' => InspectionStatus::AwaitingApproval]);

        $this->expectException(ValidationException::class);
        $this->detach()->handle($actor, $inspection->id, $document->id);
    }

    public function test_an_unauthorized_actor_cannot_change_references(): void
    {
        [$organization, , $equipment, $inspection] = $this->scenario();
        $member = User::factory()->create(['organization_id' => $organization->id]);
        $document = EquipmentDocument::factory()->forEquipment($equipment)->create();

        $this->expectException(AuthorizationException::class);
        $this->attach()->handle($member, $inspection->id, $document->id);
    }

    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set($organization);
        $actor = User::factory()->companyAdmin()->create(['organization_id' => $organization->id]);
        $equipment = Equipment::factory()->create(['organization_id' => $organization->id]);
        $inspection = Inspection::factory()->forEquipment($equipment)->create();

        return [$organization, $actor, $equipment, $inspection];
    }

    private function attach(): AttachInspectionReferenceDocument
    {
        return app(AttachInspectionReferenceDocument::class);
    }

    private function detach(): DetachInspectionReferenceDocument
    {
        return app(DetachInspectionReferenceDocument::class);
    }
}
