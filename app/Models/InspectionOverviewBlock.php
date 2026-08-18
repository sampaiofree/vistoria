<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\InspectionOverviewBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InspectionOverviewBlock extends Model
{
    /** @use HasFactory<InspectionOverviewBlockFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id', 'organization_id', 'inspection_id', 'position', 'comment', 'recommendation', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionOverviewPhoto::class)->orderBy('slot');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
