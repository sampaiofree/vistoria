<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'document',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function clientUnits(): HasMany
    {
        return $this->hasMany(ClientUnit::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function subareas(): HasMany
    {
        return $this->hasMany(Subarea::class);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }
}
