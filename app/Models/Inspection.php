<?php

namespace App\Models;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'organization_id',
        'equipment_id',
        'previous_inspection_id',
        'number',
        'inspection_type',
        'status',
        'service_order',
        'external_report_number',
        'procedure_number',
        'atmospheric_classification',
        'scheduled_for',
        'inspected_on',
        'context_snapshot',
        'snapshot_version',
        'general_notes',
        'started_at',
        'field_completed_at',
        'reviewed_at',
        'approved_at',
        'report_generated_at',
        'released_at',
        'canceled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'inspection_type' => InspectionType::class,
            'status' => InspectionStatus::class,
            'scheduled_for' => 'date',
            'inspected_on' => 'date',
            'context_snapshot' => 'array',
            'started_at' => 'datetime',
            'field_completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'report_generated_at' => 'datetime',
            'released_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function previousInspection(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_inspection_id');
    }

    public function nextInspections(): HasMany
    {
        return $this->hasMany(self::class, 'previous_inspection_id');
    }

    public function responsibles(): HasMany
    {
        return $this->hasMany(InspectionResponsible::class)
            ->orderByDesc('is_primary')
            ->orderBy('created_at');
    }

    public function referenceDocuments(): HasMany
    {
        return $this->hasMany(InspectionReferenceDocument::class)
            ->orderByDesc('created_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(InspectionStatusHistory::class)
            ->orderBy('created_at');
    }

    public function hasResponsibility(InspectionResponsibility $responsibility): bool
    {
        return $this->responsibles()->where('responsibility', $responsibility->value)->exists();
    }

    public function hasResponsibilityForUser(User $user, InspectionResponsibility $responsibility): bool
    {
        return $this->responsibles()
            ->where('user_id', $user->getKey())
            ->where('responsibility', $responsibility->value)
            ->exists();
    }

    public function hasAnyResponsibilityForUser(User $user, InspectionResponsibility ...$responsibilities): bool
    {
        if ($responsibilities === []) {
            return false;
        }

        return $this->responsibles()
            ->where('user_id', $user->getKey())
            ->whereIn('responsibility', array_map(
                fn (InspectionResponsibility $responsibility): string => $responsibility->value,
                $responsibilities,
            ))
            ->exists();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
