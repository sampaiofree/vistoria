<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PhotoProcessingStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\InspectionOverviewPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class InspectionOverviewPhoto extends Model
{
    /** @use HasFactory<InspectionOverviewPhotoFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'public_id', 'organization_id', 'inspection_id', 'inspection_overview_block_id', 'slot', 'processing_status', 'disk',
        'original_path', 'optimized_path', 'thumbnail_path', 'original_name', 'original_mime_type', 'original_extension',
        'original_size', 'original_width', 'original_height', 'optimized_size', 'optimized_width', 'optimized_height',
        'thumbnail_size', 'thumbnail_width', 'thumbnail_height', 'checksum', 'uploaded_at', 'processed_at', 'uploaded_by', 'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'processing_status' => PhotoProcessingStatus::class,
            'uploaded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(InspectionOverviewBlock::class, 'inspection_overview_block_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isReady(): bool
    {
        return $this->processing_status === PhotoProcessingStatus::Ready;
    }
}
