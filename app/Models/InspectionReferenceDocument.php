<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionReferenceDocument extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function equipmentDocument(): BelongsTo
    {
        return $this->belongsTo(EquipmentDocument::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
