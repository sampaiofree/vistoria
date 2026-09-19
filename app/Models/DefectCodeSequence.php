<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DefectCategory;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\DefectCodeSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DefectCodeSequence extends Model
{
    /** @use HasFactory<DefectCodeSequenceFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'equipment_id',
        'category',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'category' => DefectCategory::class,
            'last_number' => 'integer',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
