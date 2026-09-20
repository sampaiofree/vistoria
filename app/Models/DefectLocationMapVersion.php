<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\DefectLocationMapVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DefectLocationMapVersion extends Model
{
    /** @use HasFactory<DefectLocationMapVersionFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id', 'organization_id', 'equipment_id', 'defect_location_map_id', 'created_for_assessment_id',
        'version', 'source_disk', 'source_path', 'source_mime_type', 'source_size', 'source_checksum',
        'source_uploaded_by', 'background_disk', 'background_path', 'background_mime_type', 'background_size',
        'background_width', 'background_height', 'background_checksum', 'processing_status', 'processing_error',
        'processed_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'source_size' => 'integer',
            'background_size' => 'integer',
            'background_width' => 'integer',
            'background_height' => 'integer',
            'processing_status' => InspectionLocationMapProcessingStatus::class,
            'processed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(DefectLocationMap::class, 'defect_location_map_id');
    }

    public function createdForAssessment(): BelongsTo
    {
        return $this->belongsTo(DefectAssessment::class, 'created_for_assessment_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(DefectAssessment::class, 'defect_location_map_version_id');
    }

    public function sourceUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_uploaded_by');
    }

    public function isReady(): bool
    {
        return $this->processing_status === InspectionLocationMapProcessingStatus::Ready
            && $this->background_path !== null;
    }
}
