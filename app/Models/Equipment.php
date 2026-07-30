<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Enums\InspectionStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    use HasPublicId;
    use SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'organization_id',
        'client_id',
        'client_unit_id',
        'area_id',
        'subarea_id',
        'tag',
        'normalized_tag',
        'defect_code_prefix',
        'name',
        'description',
        'manufacturer',
        'model',
        'serial_number',
        'asset_code',
        'abc_code',
        'installation_location',
        'commissioned_at',
        'status',
        'notes',
        'decommissioned_at',
        'decommissioned_by',
        'decommission_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'commissioned_at' => 'date',
            'decommissioned_at' => 'datetime',
            'status' => EquipmentStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'client_unit_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function subarea(): BelongsTo
    {
        return $this->belongsTo(Subarea::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function decommissioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decommissioned_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class)
            ->orderByDesc('created_at');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)
            ->orderByDesc('created_at');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class)
            ->orderByDesc('created_at');
    }

    public function releasedInspections(): HasMany
    {
        return $this->inspections()
            ->where('status', InspectionStatus::Released->value);
    }

    public function currentDocuments(): HasMany
    {
        return $this->documents()
            ->where('is_current', true);
    }

    public function isActive(): bool
    {
        return $this->status === EquipmentStatus::Active;
    }

    public function hasOperationalStructure(): bool
    {
        return $this->client?->isActive() === true
            && $this->unit?->isOperationallyActive() === true
            && $this->area?->isOperationallyActive() === true
            && (
                $this->subarea_id === null
                || $this->subarea?->isOperationallyActive() === true
            );
    }

    public function canReceiveInspection(): bool
    {
        return $this->isActive()
            && $this->hasOperationalStructure();
    }
}
