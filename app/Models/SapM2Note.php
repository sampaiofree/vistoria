<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SapM2Note extends Model
{
    use BelongsToOrganization, HasPublicId;

    protected $fillable = ['public_id', 'organization_id', 'equipment_id', 'sap_number', 'created_by', 'updated_by'];

    public function equipment(): BelongsTo { return $this->belongsTo(Equipment::class); }
    public function links(): HasMany { return $this->hasMany(InspectionClassificationM2Link::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
