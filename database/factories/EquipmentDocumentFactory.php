<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<EquipmentDocument> */
final class EquipmentDocumentFactory extends Factory
{
    protected $model = EquipmentDocument::class;

    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'organization_id' => fn (array $attributes) => Equipment::query()->findOrFail($attributes['equipment_id'])->organization_id,
            'document_group' => (string) Str::ulid(),
            'title' => 'Desenho técnico',
            'revision' => '1',
            'is_current' => true,
        ];
    }

    public function forEquipment(Equipment $equipment): static
    {
        return $this->state(['organization_id' => $equipment->organization_id, 'equipment_id' => $equipment->id]);
    }

    public function revisionOf(EquipmentDocument $document): static
    {
        return $this->state([
            'organization_id' => $document->organization_id,
            'equipment_id' => $document->equipment_id,
            'document_group' => $document->document_group,
        ]);
    }
}
