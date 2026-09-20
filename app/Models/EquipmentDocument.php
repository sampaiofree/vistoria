<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\EquipmentDocumentType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\EquipmentDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EquipmentDocument extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<EquipmentDocumentFactory> */
    use HasFactory;

    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'organization_id',
        'equipment_id',
        'document_group',
        'document_type',
        'title',
        'document_number',
        'revision',
        'description',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'checksum',
        'is_current',
        'status',
        'uploaded_by',
        'issued_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (EquipmentDocument $document): void {
            $document->document_group ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'document_type' => EquipmentDocumentType::class,
            'is_current' => 'boolean',
            'status' => DocumentStatus::class,
            'issued_at' => 'date',
            'size' => 'integer',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
