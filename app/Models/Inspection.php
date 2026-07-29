<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Inspection extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $inspection): void {
            $inspection->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => InspectionStatus::class,
            'inspected_on' => 'date',
            'started_at' => 'datetime',
            'field_completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'report_generated_at' => 'datetime',
            'released_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function responsibles(): HasMany
    {
        return $this->hasMany(InspectionResponsible::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(InspectionStatusHistory::class);
    }

    public function hasActiveResponsible(InspectionResponsibility $responsibility, ?User $user = null): bool
    {
        return $this->responsibles()
            ->where('responsibility', $responsibility->value)
            ->when($user, fn ($query) => $query->where('user_id', $user->getKey()))
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::Active->value))
            ->exists();
    }
}
