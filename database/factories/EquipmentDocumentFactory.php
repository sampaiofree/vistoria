<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\EquipmentDocumentType;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EquipmentDocument>
 */
class EquipmentDocumentFactory extends Factory
{
    protected $model = EquipmentDocument::class;

    public function definition(): array
    {
        $fileId = (string) Str::ulid();

        return [
            'organization_id' => Organization::factory(),
            'equipment_id' => fn (array $attributes): int => $this->createEquipment($attributes)->getKey(),
            'document_group' => (string) Str::ulid(),
            'document_type' => fake()->randomElement(EquipmentDocumentType::cases()),
            'title' => fake()->sentence(4),
            'document_number' => fake()->optional()->bothify('DOC-####'),
            'revision' => fake()->optional()->bothify('R##'),
            'description' => fake()->optional()->sentence(8),
            'disk' => 'equipment_documents',
            'path' => 'testing/'.$fileId.'.pdf',
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1024,
            'checksum' => hash('sha256', $fileId),
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

    private function createEquipment(array $attributes): Equipment
    {
        return Equipment::factory()->create([
            'organization_id' => $this->organizationId($attributes),
        ]);
    }

    private function organizationId(array $attributes): int
    {
        $organization = $attributes['organization_id'];

        if ($organization instanceof Organization) {
            return (int) $organization->getKey();
        }

        if ($organization instanceof Factory) {
            return (int) $organization->create()->getKey();
        }

        return (int) $organization;
    }
}
