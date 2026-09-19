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

    public function isSuperAdmin(): bool
    {
        return $this->account_type === UserAccountType::SuperAdmin;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->account_type === UserAccountType::CompanyAdmin;
    }

    public function operationalRoleLabel(): string
    {
        return $this->operational_role?->label() ?? 'Papel não definido';
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

            if (! $user->isSuperAdmin() && blank($user->organization_id)) {
                throw new LogicException('Non-super-admin users must belong to an organization.');
            }
        });
    }
}
