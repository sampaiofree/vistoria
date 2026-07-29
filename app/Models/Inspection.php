<?php

namespace App\Models;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['organization_id', 'status'];

    protected function casts(): array
    {
        return ['status' => InspectionStatus::class];
    }

    public function responsibles(): HasMany
    {
        return $this->hasMany(InspectionResponsible::class);
    }

    public function hasResponsibility(InspectionResponsibility $responsibility): bool
    {
        return $this->responsibles()->where('responsibility', $responsibility->value)->exists();
    }
}
