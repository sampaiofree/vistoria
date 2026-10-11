<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicId, Notifiable;

    protected $fillable = [
        'organization_id',
        'name',
        'profile_photo_path',
        'email',
        'password',
        'must_change_password',
        'account_type',
        'operational_role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'account_type' => UserAccountType::class,
            'operational_role' => OperationalRole::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inspectionResponsibles(): HasMany
    {
        return $this->hasMany(InspectionResponsible::class);
    }

    public function operationalRoles(): HasMany
    {
        return $this->hasMany(UserOperationalRole::class);
    }

    public function hasOperationalRole(OperationalRole $role): bool
    {
        if ($this->relationLoaded('operationalRoles')) {
            return $this->operationalRoles->contains(fn (UserOperationalRole $item): bool => $item->role === $role);
        }

        return $this->operationalRoles()->where('role', $role->value)->exists();
    }

    /** @return list<string> */
    public function operationalRoleValues(): array
    {
        return $this->operationalRoles->pluck('role')->map(fn (OperationalRole $role): string => $role->value)->all();
    }

    public function scopeWithOperationalRole(Builder $query, OperationalRole $role): Builder
    {
        return $query->whereHas('operationalRoles', fn (Builder $roles) => $roles->where('role', $role->value));
    }

    /** @param list<string> $roles */
    public function syncOperationalRoles(array $roles): void
    {
        $this->operationalRoles()->whereNotIn('role', $roles)->delete();
        foreach ($roles as $role) {
            $this->operationalRoles()->firstOrCreate(['role' => $role]);
        }
        $this->unsetRelation('operationalRoles');
    }

    public function isSuperAdmin(): bool
    {
        return $this->account_type === UserAccountType::SuperAdmin;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->account_type === UserAccountType::CompanyAdmin;
    }

    public function isClient(): bool
    {
        return $this->account_type === UserAccountType::Client;
    }

    public function operationalRoleLabel(): string
    {
        $labels = $this->operationalRoles->pluck('role')
            ->map(fn (OperationalRole $role): string => $role->label())
            ->all();

        return $labels === [] ? 'Papel não definido' : implode(', ', $labels);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function belongsToOrganization(int $organizationId): bool
    {
        return $this->organization_id !== null
            && (int) $this->organization_id === $organizationId;
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->isSuperAdmin() && filled($user->organization_id)) {
                throw new LogicException('Super-admin users must not belong to an organization.');
            }

            if ($user->isSuperAdmin() && $user->operational_role !== null) {
                throw new LogicException('Super-admin users must not have an operational role.');
            }

            if ($user->isClient() && $user->operational_role !== null) {
                throw new LogicException('Client users must not have an operational role.');
            }

            if (! $user->isSuperAdmin() && blank($user->organization_id)) {
                throw new LogicException('Non-super-admin users must belong to an organization.');
            }
        });

        // Keeps legacy single-role factories and writes consistent during the migration.
        static::saved(function (self $user): void {
            if ($user->wasChanged('operational_role')) {
                $user->syncOperationalRoles($user->operational_role === null ? [] : [$user->operational_role->value]);
            }
        });
    }
}
