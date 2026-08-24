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

final class DefectClassification extends Model
{
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id', 'organization_id', 'defect_category_id', 'code', 'name', 'description', 'color', 'status', 'position', 'severity_rank', 'lower_limit', 'upper_limit', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'position' => 'integer',
            'severity_rank' => 'integer',
            'lower_limit' => 'integer',
            'upper_limit' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DefectCategory::class, 'defect_category_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(DefectAssessment::class, 'defect_classification_id');
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
