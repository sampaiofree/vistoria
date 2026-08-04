<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\DefectStatus;
use App\Enums\InspectionStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\DefectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Defect extends Model
{
    /** @use HasFactory<DefectFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'organization_id',
        'equipment_id',
        'first_inspection_id',
        'code',
        'category',
        'sequence_number',
        'title',
        'origin_description',
        'status',
        'repaired_at',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => DefectCategory::class,
            'status' => DefectStatus::class,
            'sequence_number' => 'integer',
            'repaired_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function firstInspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'first_inspection_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(DefectAssessment::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function latestAssessment(): HasOne
    {
        return $this->hasOne(DefectAssessment::class)
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->whereHas('inspection', fn ($query) => $query->where('status', '!=', InspectionStatus::Canceled->value))
            ->orderByDesc('assessed_at')
            ->orderByDesc('id');
    }

    public function draftAssessments(): HasMany
    {
        return $this->hasMany(DefectAssessment::class)
            ->where('status', DefectAssessmentStatus::Draft->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->status === DefectStatus::Active;
    }

    public function isRepaired(): bool
    {
        return $this->status === DefectStatus::Repaired;
    }
}
