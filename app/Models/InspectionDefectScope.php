<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionDefectScope extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'inspection_id', 'defect_id', 'source_assessment_id',
        'requires_reinspection', 'historical_due_date',
    ];

    protected function casts(): array
    {
        return ['requires_reinspection' => 'boolean', 'historical_due_date' => 'date'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(Defect::class);
    }

    public function sourceAssessment(): BelongsTo
    {
        return $this->belongsTo(DefectAssessment::class, 'source_assessment_id');
    }
}
