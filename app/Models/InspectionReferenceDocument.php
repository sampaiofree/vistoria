<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\InspectionReferenceDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionReferenceDocument extends Model
{
    /** @use HasFactory<InspectionReferenceDocumentFactory> */
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'inspection_id',
        'equipment_document_id',
        'added_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(EquipmentDocument::class, 'equipment_document_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
