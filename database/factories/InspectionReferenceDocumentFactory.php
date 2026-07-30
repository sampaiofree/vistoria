<?php

namespace Database\Factories;

use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionReferenceDocument>
 */
class InspectionReferenceDocumentFactory extends Factory
{
    protected $model = InspectionReferenceDocument::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'inspection_id' => null,
            'equipment_document_id' => null,
            'added_by' => null,
            'created_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (InspectionReferenceDocument $reference): void {
            if (blank($reference->inspection_id)) {
                $inspection = Inspection::factory()->create([
                    'organization_id' => $this->organizationId($reference->organization_id),
                ]);

                $reference->inspection_id = $inspection->getKey();
                $reference->organization_id = $inspection->organization_id;
            }

            $inspection = Inspection::query()
                ->with('equipment')
                ->findOrFail($reference->inspection_id);

            $reference->organization_id = $inspection->organization_id;

            if (blank($reference->equipment_document_id)) {
                $reference->equipment_document_id = EquipmentDocument::factory()
                    ->forEquipment($inspection->equipment)
                    ->create()
                    ->getKey();
            }

            if (blank($reference->added_by)) {
                $reference->added_by = User::factory()->create([
                    'organization_id' => $inspection->organization_id,
                ])->getKey();
            }
        });
    }

    public function forInspection(Inspection $inspection, ?EquipmentDocument $document = null): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->getKey(),
            'equipment_document_id' => $document?->getKey() ?? EquipmentDocument::factory()
                ->forEquipment($inspection->equipment)
                ->create()
                ->getKey(),
        ]);
    }

    private function organizationId(mixed $organization): int
    {
        if ($organization instanceof Organization) {
            return (int) $organization->getKey();
        }

        if ($organization instanceof Factory) {
            return (int) $organization->create()->getKey();
        }

        return (int) $organization;
    }
}
