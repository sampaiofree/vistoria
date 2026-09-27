<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Models\Inspection;
use App\Models\InspectionClassificationM2Link;
use App\Models\InspectionSpecialAssessmentNote;
use App\Models\SapM2Note;
use App\Models\User;
use App\Services\Inspections\RecordClassificationChange;
use App\Services\Reports\BuildInspectionClassificationSummary;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateInspectionClassificationM2Links
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly BuildInspectionClassificationSummary $summary,
    ) {}

    /** @param array{links:list<array{category:string,classification_code:string,sap_number:?string}>,special_rows?:list<array{assessment_public_id:string,service:?string,priority:?string,note:?string}>} $data */
    public function handle(User $actor, Inspection $inspection, array $data): void
    {
        DB::transaction(function () use ($actor, $inspection, $data): void {
            $inspection = Inspection::query()->forOrganization($this->tenant->id())->lockForUpdate()->findOrFail($inspection->id);
            Gate::forUser($actor->fresh())->authorize('manageClassificationM2', $inspection);
            $audit = app(RecordClassificationChange::class);
            $before = $audit->notes($inspection);
            $eligibleGroups = collect($this->summary->build($inspection)['categories'])
                ->flatMap(fn (array $category): array => collect($category['rows'])
                    ->filter(fn (array $row): bool => $row['defect_count'] > 0)
                    ->mapWithKeys(fn (array $row): array => [$category['code'].'|'.$row['classification_code'] => true])
                    ->all())
                ->all();

            foreach ($data['links'] as $row) {
                $groupKey = $row['category'].'|'.$row['classification_code'];
                if (! isset($eligibleGroups[$groupKey])) {
                    throw ValidationException::withMessages([
                        'links' => 'A Nota M2 só pode ser vinculada a uma classificação com avarias publicadas nesta inspeção.',
                    ]);
                }
                $query = InspectionClassificationM2Link::query()
                    ->forOrganization($this->tenant->id())
                    ->where('inspection_id', $inspection->id)
                    ->where('category', $row['category'])
                    ->where('classification_code', $row['classification_code']);

                if ($row['sap_number'] === null) {
                    $query->delete();

                    continue;
                }

                $note = SapM2Note::query()->firstOrCreate([
                    'organization_id' => $this->tenant->id(),
                    'equipment_id' => $inspection->equipment_id,
                    'sap_number' => $row['sap_number'],
                ], [
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $note->update(['updated_by' => $actor->id]);

                $link = InspectionClassificationM2Link::query()->firstOrNew([
                    'organization_id' => $this->tenant->id(),
                    'inspection_id' => $inspection->id,
                    'category' => $row['category'],
                    'classification_code' => $row['classification_code'],
                ]);
                $link->sap_m2_note_id = $note->id;
                $link->created_by ??= $actor->id;
                $link->save();
            }

            $eligibleSpecialRows = collect($this->summary->build($inspection)['special_assessment_rows'])
                ->keyBy('assessment_public_id');

            foreach ($data['special_rows'] ?? [] as $row) {
                $eligible = $eligibleSpecialRows->get($row['assessment_public_id']);
                if ($eligible === null) {
                    throw ValidationException::withMessages([
                        'special_rows' => 'A nota só pode ser vinculada a uma avaria elegível desta inspeção.',
                    ]);
                }

                $query = InspectionSpecialAssessmentNote::query()
                    ->forOrganization($this->tenant->id())
                    ->where('inspection_id', $inspection->id)
                    ->where('defect_assessment_id', $eligible['assessment_id']);

                if ($row['service'] === null && $row['priority'] === null && $row['note'] === null) {
                    $query->delete();

                    continue;
                }

                $specialNote = InspectionSpecialAssessmentNote::query()->firstOrNew([
                    'organization_id' => $this->tenant->id(),
                    'inspection_id' => $inspection->id,
                    'defect_assessment_id' => $eligible['assessment_id'],
                ]);
                $specialNote->fill([
                    'service' => $row['service'],
                    'priority' => $row['priority'],
                    'note' => $row['note'],
                    'updated_by' => $actor->id,
                ]);
                $specialNote->created_by ??= $actor->id;
                $specialNote->save();
            }
            $audit->record($inspection, $actor, 'notas', $before, $audit->notes($inspection));
        });
    }
}
