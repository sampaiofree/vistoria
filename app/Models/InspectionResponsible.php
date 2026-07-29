<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionResponsibility;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionResponsible extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['responsibility' => InspectionResponsibility::class, 'assigned_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
