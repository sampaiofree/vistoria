<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class InspectionLocationMarkerPhoto extends Pivot
{
    public $incrementing = true;

    public $timestamps = false;

    public const UPDATED_AT = null;

    protected $table = 'inspection_location_marker_photos';

    protected $fillable = ['organization_id', 'inspection_id', 'inspection_location_marker_id', 'assessment_photo_id', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(InspectionLocationMarker::class, 'inspection_location_marker_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(AssessmentPhoto::class, 'assessment_photo_id');
    }
}
