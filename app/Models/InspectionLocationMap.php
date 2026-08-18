<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionLocationMapSourceKind;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\InspectionLocationMapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class InspectionLocationMap extends Model
{
    /** @use HasFactory<InspectionLocationMapFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'public_id', 'organization_id', 'equipment_id', 'inspection_id', 'defect_category_id', 'equipment_document_id',
        'title', 'description', 'source_kind', 'source_page', 'source_crop', 'reference_snapshot', 'source_disk', 'source_path',
        'source_mime_type', 'source_size', 'source_checksum', 'background_disk', 'background_path', 'background_mime_type',
        'background_size', 'background_width', 'background_height', 'background_checksum', 'processing_status', 'processing_error',
        'processed_at', 'geometry_schema_version', 'position', 'lock_version', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'source_kind' => InspectionLocationMapSourceKind::class,
            'processing_status' => InspectionLocationMapProcessingStatus::class,
            'source_page' => 'integer',
            'source_crop' => 'array',
            'reference_snapshot' => 'array',
            'source_size' => 'integer',
            'background_size' => 'integer',
            'background_width' => 'integer',
            'background_height' => 'integer',
            'processed_at' => 'datetime',
            'geometry_schema_version' => 'integer',
            'position' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DefectCategory::class, 'defect_category_id');
    }

    public function equipmentDocument(): BelongsTo
    {
        return $this->belongsTo(EquipmentDocument::class);
    }

    public function markers(): HasMany
    {
        return $this->hasMany(InspectionLocationMarker::class)->orderBy('position')->orderBy('id');
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
