<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\DefectAssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DefectAssessment extends Model
{
    /** @use HasFactory<DefectAssessmentFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'organization_id',
        'equipment_id',
        'defect_id',
        'inspection_id',
        'previous_assessment_id',
        'condition',
        'status',
        'location_description',
        'comment',
        'recommendation',
        'reason',
        'internal_notes',
        'defect_snapshot',
        'snapshot_version',
        'assessed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'condition' => DefectAssessmentCondition::class,
            'status' => DefectAssessmentStatus::class,
            'defect_snapshot' => 'array',
            'snapshot_version' => 'integer',
            'assessed_at' => 'datetime',
        ];
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(Defect::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function previousAssessment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_assessment_id');
    }

    public function nextAssessments(): HasMany
    {
        return $this->hasMany(self::class, 'previous_assessment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === DefectAssessmentStatus::Draft;
    }

    public function isComplete(): bool
    {
        return $this->status === DefectAssessmentStatus::Complete;
    }

    public function requiresReason(): bool
    {
        return $this->condition->requiresReason();
    }
}
