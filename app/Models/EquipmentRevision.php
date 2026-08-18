<?php

namespace App\Models;

use App\Enums\EquipmentRevisionEmissionType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class EquipmentRevision extends Model
{
    use BelongsToOrganization, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'public_id',
        'organization_id',
        'equipment_id',
        'emission_type',
        'revision_date',
        'preparer_id',
        'reviewer_id',
        'approver_id',
        'releaser_id',
        'preparer_name',
        'reviewer_name',
        'approver_name',
        'releaser_name',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'emission_type' => EquipmentRevisionEmissionType::class,
            'revision_date' => 'date',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preparer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'releaser_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
