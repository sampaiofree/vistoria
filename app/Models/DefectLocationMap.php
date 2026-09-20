<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\DefectLocationMapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DefectLocationMap extends Model
{
    /** @use HasFactory<DefectLocationMapFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id', 'organization_id', 'equipment_id', 'defect_id', 'created_by', 'updated_by',
    ];

    public function defect(): BelongsTo
    {
        return $this->belongsTo(Defect::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DefectLocationMapVersion::class)->orderBy('version')->orderBy('id');
    }
}
