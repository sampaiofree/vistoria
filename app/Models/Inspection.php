<?php

namespace App\Models;

use App\Enums\InspectionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use BelongsToOrganization, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => InspectionStatus::class, 'context_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $inspection) => $inspection->public_id ??= (string) Str::ulid());
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function referenceDocuments(): HasMany
    {
        return $this->hasMany(InspectionReferenceDocument::class);
    }
}
