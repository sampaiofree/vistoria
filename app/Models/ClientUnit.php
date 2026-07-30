<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\ClientUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientUnit extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ClientUnitFactory> */
    use HasFactory;

    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'client_id',
        'name',
        'code',
        'normalized_code',
        'timezone',
        'address_line',
        'address_number',
        'district',
        'postal_code',
        'city',
        'state',
        'country_code',
        'status',
        'notes',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
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
            && $this->client !== null
            && $this->client->isActive();
    }
}
