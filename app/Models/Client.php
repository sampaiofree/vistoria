<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'legal_name',
        'document',
        'email',
        'phone',
        'status',
        'notes',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(ClientUnit::class);
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
}
