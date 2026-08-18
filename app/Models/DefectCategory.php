<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DefectCategory extends Model
{
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id', 'organization_id', 'name', 'code', 'description', 'status', 'requires_location_map', 'position', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'requires_location_map' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(DefectClassification::class)->orderBy('position')->orderBy('id');
    }

    public function gutOptions(): HasMany
    {
        return $this->hasMany(DefectCategoryGutOption::class, 'defect_category_id')
            ->orderBy('criterion')
            ->orderBy('score')
            ->orderBy('id');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class, 'defect_category_id');
    }

    public function codeSequences(): HasMany
    {
        return $this->hasMany(DefectCodeSequence::class, 'defect_category_id');
    }

    public function locationMaps(): HasMany
    {
        return $this->hasMany(InspectionLocationMap::class, 'defect_category_id')->orderBy('position')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RegistrationStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === RegistrationStatus::Active;
    }
}
