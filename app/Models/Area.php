<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'client_unit_id',
        'name',
        'code',
        'normalized_code',
        'status',
        'description',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'client_unit_id');
    }

    public function subareas(): HasMany
    {
        return $this->hasMany(Subarea::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === RegistrationStatus::Active;
    }

    public function isOperationallyActive(): bool
    {
        return $this->isActive()
            && $this->unit !== null
            && $this->unit->isOperationallyActive();
    }
}
