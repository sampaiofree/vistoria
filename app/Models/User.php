<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'account_type',
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
            'account_type' => UserAccountType::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->account_type === UserAccountType::SuperAdmin;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->account_type === UserAccountType::CompanyAdmin;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->isSuperAdmin() && filled($user->organization_id)) {
                throw new LogicException('Super-admin users must not belong to an organization.');
            }

            if (! $user->isSuperAdmin() && blank($user->organization_id)) {
                throw new LogicException('Non-super-admin users must belong to an organization.');
            }
        });
    }
}
