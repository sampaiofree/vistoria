<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InspectionClassificationM2Link extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'inspection_id', 'category', 'classification_code', 'sap_m2_note_id', 'created_by'];

    public function inspection(): BelongsTo { return $this->belongsTo(Inspection::class); }
    public function note(): BelongsTo { return $this->belongsTo(SapM2Note::class, 'sap_m2_note_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
