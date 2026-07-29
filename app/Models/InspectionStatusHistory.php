<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

final class InspectionStatusHistory extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['from_status' => InspectionStatus::class, 'to_status' => InspectionStatus::class, 'metadata' => 'array', 'created_at' => 'datetime'];
    }
}
