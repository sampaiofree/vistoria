<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\SubareaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subarea extends Model
{
    /** @use HasFactory<SubareaFactory> */
    use HasFactory;
    use BelongsToOrganization;
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'area_id',
        'name',
        'code',
        'normalized_code',
        'status',
        'description',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
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
            && $this->area !== null
            && $this->area->isOperationallyActive();
    }
}
