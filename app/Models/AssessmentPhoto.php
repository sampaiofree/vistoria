<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssessmentPhotoType;
use App\Enums\PhotoProcessingStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class AssessmentPhoto extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id', 'organization_id', 'inspection_id', 'defect_assessment_id', 'photo_type', 'caption', 'position',
        'processing_status', 'disk', 'original_path', 'optimized_path', 'thumbnail_path', 'original_name', 'original_mime_type',
        'original_extension', 'original_size', 'original_width', 'original_height', 'optimized_size', 'optimized_width', 'optimized_height',
        'thumbnail_size', 'thumbnail_width', 'thumbnail_height', 'checksum', 'captured_at', 'uploaded_at', 'processed_at', 'uploaded_by', 'processing_error',
    ];

    protected static function booted(): void
    {
        self::creating(fn (self $photo): ?string => $photo->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['photo_type' => AssessmentPhotoType::class, 'processing_status' => PhotoProcessingStatus::class, 'captured_at' => 'datetime', 'uploaded_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DefectAssessment::class, 'defect_assessment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function locationMarkers(): BelongsToMany
    {
        return $this->belongsToMany(InspectionLocationMarker::class, 'inspection_location_marker_photos', 'assessment_photo_id', 'inspection_location_marker_id')
            ->using(InspectionLocationMarkerPhoto::class)
            ->withPivot(['organization_id', 'inspection_id', 'position', 'created_at'])
            ->orderByPivot('position');
    }

    public function isReady(): bool
    {
        return $this->processing_status === PhotoProcessingStatus::Ready;
    }
}
