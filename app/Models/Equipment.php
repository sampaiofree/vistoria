<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use BelongsToOrganization, HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (self $equipment) => $equipment->public_id ??= (string) Str::ulid());
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class);
    }
}
