<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DefectCategory;
use App\Enums\MeasurementUnit;
use App\Enums\QuantityCalculationMode;
use App\Enums\QuantityCalculationType;
use App\Enums\StructuralRecoveryElement;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\DefectAssessmentQuantityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DefectAssessmentQuantity extends Model
{
    /** @use HasFactory<DefectAssessmentQuantityFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'organization_id',
        'inspection_id',
        'defect_assessment_id',
        'description',
        'category',
        'calculation_type',
        'rec_element',
        'inputs',
        'quantity',
        'unit_value',
        'measurement_value',
        'measurement_unit',
        'mode',
        'formula_version',
        'formula_snapshot',
        'position',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => DefectCategory::class,
            'calculation_type' => QuantityCalculationType::class,
            'rec_element' => StructuralRecoveryElement::class,
            'inputs' => 'array',
            'quantity' => 'decimal:16',
            'unit_value' => 'decimal:16',
            'measurement_value' => 'decimal:16',
            'measurement_unit' => MeasurementUnit::class,
            'mode' => QuantityCalculationMode::class,
            'formula_version' => 'integer',
            'formula_snapshot' => 'array',
            'position' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DefectAssessment::class, 'defect_assessment_id');
    }

    public function value(): float
    {
        return (float) $this->measurement_value;
    }

    public function hasFractionalMultiplier(): bool
    {
        return str_contains(rtrim(rtrim((string) $this->quantity, '0'), '.'), '.');
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            ...($this->formula_snapshot ?? []),
            'source' => data_get($this->formula_snapshot, 'source', 'native_quantity_catalog'),
            'formula_version' => $this->formula_version,
            'position' => $this->position,
            'description' => $this->description,
            'category' => $this->category?->value,
            'calculation_type' => $this->calculation_type?->value,
            'mode' => $this->mode?->value,
            'element' => $this->rec_element === null ? null : [
                'code' => $this->rec_element->value,
                'label' => $this->rec_element->label(),
            ],
            'inputs' => $this->inputs ?? [],
            'quantity' => $this->quantity,
            'unit_value' => $this->unit_value,
            'total' => $this->measurement_value,
            'measurement_unit' => $this->measurement_unit->value,
        ];
    }
}
