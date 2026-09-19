<?php

namespace App\Models;

use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use BelongsToOrganization, HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'organization_id',
        'equipment_id',
        'previous_inspection_id',
        'number',
        'inspection_type',
        'status',
        'service_order',
        'external_report_number',
        'report_designer',
        'designer_i_report_number',
        'procedure_number',
        'atmospheric_classification',
        'planned_start_on', // Data inicial planejada para a inspeção.
        'planned_end_on', // Prazo final planejado para a inspeção.
        'inspected_on', // Data em que a inspeção foi realizada em campo.
        'context_snapshot',
        'snapshot_version',
        'general_notes',
        'started_at', // Data e hora de início da execução da inspeção.
        'field_completed_at', // Data e hora de conclusão da etapa de campo.
        'reviewed_at', // Data e hora de conclusão da revisão.
        'approved_at', // Data e hora da aprovação da inspeção.
        'report_generated_at', // Data e hora de geração do relatório.
        'report_date', // Data oficial exibida no relatório.
        'emission_type', //Tipo de emissão
        'first_page_text_template',
        'released_at', // Data e hora da liberação final da inspeção.
        'canceled_at', // Data e hora do cancelamento, quando aplicável.
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        // created_at e updated_at são mantidos automaticamente pelo Eloquent.
        return [
            'inspection_type' => InspectionType::class,
            'status' => InspectionStatus::class,
            'planned_start_on' => 'date',
            'planned_end_on' => 'date',
            'inspected_on' => 'date',
            'context_snapshot' => 'array',
            'started_at' => 'datetime',
            'field_completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'report_generated_at' => 'datetime',
            'report_date' => 'date',
            'emission_type' => EquipmentRevisionEmissionType::class,
            'released_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function previousInspection(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_inspection_id');
    }

    public function nextInspections(): HasMany
    {
        return $this->hasMany(self::class, 'previous_inspection_id');
    }

    public function responsibles(): HasMany
    {
        return $this->hasMany(InspectionResponsible::class)
            ->orderByDesc('is_primary')
            ->orderBy('created_at');
    }

    public function referenceDocuments(): HasMany
    {
        return $this->hasMany(InspectionReferenceDocument::class)
            ->orderByDesc('created_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(InspectionStatusHistory::class)
            ->orderBy('created_at');
    }

    public function defectAssessments(): HasMany
    {
        return $this->hasMany(DefectAssessment::class)
            ->orderBy('assessed_at');
    }

    public function assessmentPhotos(): HasMany
    {
        return $this->hasMany(AssessmentPhoto::class);
    }

    public function overviewBlocks(): HasMany
    {
        return $this->hasMany(InspectionOverviewBlock::class)->orderBy('position');
    }

    public function overviewPhotos(): HasMany
    {
        return $this->hasMany(InspectionOverviewPhoto::class)->orderBy('slot');
    }

    public function locationMaps(): HasMany
    {
        return $this->hasMany(InspectionLocationMap::class)->orderBy('position')->orderBy('id');
    }

    public function locationMarkers(): HasMany
    {
        return $this->hasMany(InspectionLocationMarker::class)->orderBy('position')->orderBy('id');
    }

    public function hasResponsibility(InspectionResponsibility $responsibility): bool
    {
        return $this->responsibles()->where('responsibility', $responsibility->value)->exists();
    }

    public function hasResponsibilityForUser(User $user, InspectionResponsibility $responsibility): bool
    {
        return $this->responsibles()
            ->where('user_id', $user->getKey())
            ->where('responsibility', $responsibility->value)
            ->exists();
    }

    public function hasPrimaryResponsibility(InspectionResponsibility $responsibility): bool
    {
        return $this->responsibles()
            ->where('responsibility', $responsibility->value)
            ->where('is_primary', true)
            ->exists();
    }

    public function hasAnyResponsibilityForUser(User $user, InspectionResponsibility ...$responsibilities): bool
    {
        if ($responsibilities === []) {
            return false;
        }

        return $this->responsibles()
            ->where('user_id', $user->getKey())
            ->whereIn('responsibility', array_map(
                fn (InspectionResponsibility $responsibility): string => $responsibility->value,
                $responsibilities,
            ))
            ->exists();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
