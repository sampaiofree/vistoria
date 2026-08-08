<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DefectRelationType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DefectRelation extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'equipment_id',
        'source_defect_id',
        'target_defect_id',
        'relation_type',
        'notes',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['relation_type' => DefectRelationType::class, 'created_at' => 'datetime'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function sourceDefect(): BelongsTo
    {
        return $this->belongsTo(Defect::class, 'source_defect_id');
    }

    public function targetDefect(): BelongsTo
    {
        return $this->belongsTo(Defect::class, 'target_defect_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
