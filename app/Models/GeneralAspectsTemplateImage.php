<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PhotoProcessingStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GeneralAspectsTemplateImage extends Model
{
    use BelongsToOrganization, HasPublicId;

    protected $fillable = [
        'public_id', 'organization_id', 'template_id', 'draft_token', 'processing_status', 'disk',
        'original_path', 'optimized_path', 'thumbnail_path', 'original_name', 'original_mime_type',
        'original_extension', 'original_size', 'original_width', 'original_height',
        'optimized_size', 'optimized_width', 'optimized_height', 'thumbnail_size',
        'thumbnail_width', 'thumbnail_height', 'checksum', 'uploaded_at', 'processed_at',
        'unreferenced_at', 'uploaded_by', 'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'processing_status' => PhotoProcessingStatus::class,
            'uploaded_at' => 'datetime',
            'processed_at' => 'datetime',
            'unreferenced_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(GeneralAspectsTemplate::class);
    }

    public function isReady(): bool
    {
        return $this->processing_status === PhotoProcessingStatus::Ready;
    }
}
