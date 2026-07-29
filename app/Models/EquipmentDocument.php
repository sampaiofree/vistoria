<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EquipmentDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class EquipmentDocument extends Model
{
    /** @use HasFactory<EquipmentDocumentFactory> */
    use BelongsToOrganization, HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->public_id ??= (string) Str::ulid();
            $document->document_group ??= (string) Str::ulid();
        });
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
