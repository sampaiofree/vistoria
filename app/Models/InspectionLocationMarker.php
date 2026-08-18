<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\InspectionLocationMarkerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class InspectionLocationMarker extends Model
{
    /** @use HasFactory<InspectionLocationMarkerFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'public_id', 'organization_id', 'equipment_id', 'inspection_id', 'inspection_location_map_id', 'defect_assessment_id',
        'label', 'geometry', 'style', 'position', 'lock_version', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['geometry' => 'array', 'style' => 'array', 'position' => 'integer', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        self::deleting(function (InspectionLocationMarker $marker): void {
            if (! $marker->isForceDeleting()) {
                $marker->forceFill(['active_slot' => null])->saveQuietly();
            }
        });

        self::restoring(function (InspectionLocationMarker $marker): void {
            $marker->forceFill(['active_slot' => 1]);
        });
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(InspectionLocationMap::class, 'inspection_location_map_id');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DefectAssessment::class, 'defect_assessment_id');
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentPhoto::class, 'inspection_location_marker_photos', 'inspection_location_marker_id', 'assessment_photo_id')
            ->using(InspectionLocationMarkerPhoto::class)
            ->withPivot(['organization_id', 'inspection_id', 'position', 'created_at'])
            ->orderByPivot('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
