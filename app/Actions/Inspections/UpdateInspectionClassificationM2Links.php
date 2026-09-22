<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Models\Inspection;
use App\Models\InspectionClassificationM2Link;
use App\Models\SapM2Note;
use App\Models\User;
use App\Services\Reports\BuildInspectionClassificationSummary;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateInspectionClassificationM2Links
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly BuildInspectionClassificationSummary $summary,
    ) {}

    /** @param array{links:list<array{category:string,classification_code:string,sap_number:?string}>} $data */
    public function handle(User $actor, Inspection $inspection, array $data): void
    {
        DB::transaction(function () use ($actor, $inspection, $data): void {
            $inspection = Inspection::query()->forOrganization($this->tenant->id())->lockForUpdate()->findOrFail($inspection->id);
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

                InspectionClassificationM2Link::query()->updateOrCreate([
                    'organization_id' => $this->tenant->id(),
                    'inspection_id' => $inspection->id,
                    'category' => $row['category'],
                    'classification_code' => $row['classification_code'],
                ], [
                    'organization_id' => $this->tenant->id(),
                    'sap_m2_note_id' => $note->id,
                    'created_by' => $actor->id,
                ]);
            }
        });
    }
}
