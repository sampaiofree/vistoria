<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeasurementUnit;
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
        'quantity',
        'length',
        'height',
        'width',
        'unit_volume',
        'measurement_value',
        'measurement_unit',
        'position',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'length' => 'decimal:4',
            'height' => 'decimal:4',
            'width' => 'decimal:4',
            'unit_volume' => 'decimal:12',
            'measurement_value' => 'decimal:'.(($this->attributes['length'] ?? null) === null ? 4 : 16),
            'measurement_unit' => MeasurementUnit::class,
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
}
