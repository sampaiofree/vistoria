<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'document', 'logo_path', 'primary_color', 'icon_path',
        'timezone',
        'status',
        'suspended_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function defectLocationMaps(): HasMany
    {
        return $this->hasMany(DefectLocationMap::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === OrganizationStatus::Suspended;
    }
}
