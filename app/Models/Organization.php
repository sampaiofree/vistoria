<?php

namespace App\Models;

use App\Actions\Classification\ProvisionDefaultDefectTaxonomy;
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

    protected static function booted(): void
    {
        static::created(function (self $organization): void {
            app(ProvisionDefaultDefectTaxonomy::class)->handle($organization->getKey());
        });
    }

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

    public function defectCategories(): HasMany
    {
        return $this->hasMany(DefectCategory::class);
    }

    public function inspectionLocationMaps(): HasMany
    {
        return $this->hasMany(InspectionLocationMap::class);
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

    public function isSuspended(): bool
    {
        return $this->status === OrganizationStatus::Suspended;
    }
}
