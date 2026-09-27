<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Models\Inspection;
use App\Models\User;
use App\Services\Inspections\RecordClassificationChange;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateInspectionClassificationHeader
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    /** @param array{general_drawing:?string,procedure_number:?string,inspected_on:?string} $data */
    public function handle(User $actor, Inspection $inspection, array $data): Inspection
    {
        return DB::transaction(function () use ($actor, $inspection, $data): Inspection {
            $inspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($inspection->id);

            Gate::forUser($actor->fresh())->authorize('manageClassificationM2', $inspection);
            $fields = ['general_drawing', 'procedure_number', 'inspected_on'];
            $before = $inspection->only($fields);

            $inspection->update([
                'general_drawing' => TextNormalizer::nullableText($data['general_drawing'] ?? null),
                'procedure_number' => TextNormalizer::nullableText($data['procedure_number'] ?? null),
                'inspected_on' => $data['inspected_on'] ?? null,
                'updated_by' => $actor->id,
            ]);

            app(RecordClassificationChange::class)->record(
                $inspection, $actor, 'cabeçalho', $before, $inspection->only($fields),
            );

            return $inspection->refresh();
        });
    }
}
