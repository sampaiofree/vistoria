<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\DefectAssessmentLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DefectAssessmentLocation extends Model
{
    /** @use HasFactory<DefectAssessmentLocationFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id', 'organization_id', 'equipment_id', 'inspection_id', 'defect_assessment_id',
        'geometry', 'label', 'confirmed_at', 'confirmed_by', 'lock_version', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'confirmed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DefectAssessment::class, 'defect_assessment_id');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
