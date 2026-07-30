<?php

namespace App\Models;

use App\Enums\InspectionResponsibility;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\InspectionResponsibleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionResponsible extends Model
{
    /** @use HasFactory<InspectionResponsibleFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['organization_id', 'inspection_id', 'user_id', 'responsibility', 'is_primary', 'assigned_by', 'assigned_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'responsibility' => InspectionResponsibility::class,
            'is_primary' => 'boolean',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
