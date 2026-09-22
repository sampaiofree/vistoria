<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InspectionCorrectionRequest extends Model
{
    use BelongsToOrganization, HasPublicId;

    protected $fillable = [
        'public_id',
        'organization_id',
        'inspection_id',
        'defect_assessment_id',
        'previous_request_id',
        'parent_request_id',
        'status',
        'flow',
        'request_message',
        'response_message',
        'created_by',
        'sent_by',
        'sent_at',
        'addressed_by',
        'addressed_at',
        'closed_by',
        'closed_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'flow' => InspectionCorrectionRequestFlow::class,
            'status' => InspectionCorrectionRequestStatus::class,
            'sent_at' => 'datetime',
            'addressed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DefectAssessment::class, 'defect_assessment_id');
    }

    public function previousRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_request_id');
    }

    public function parentRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_request_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'previous_request_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_request_id')->orderBy('created_at')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function addressedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressed_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
