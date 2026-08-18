<?php

declare(strict_types=1);

namespace App\Actions\InspectionOverview;

use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateInspectionOverviewBlock
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @param array{comment?: ?string, recommendation?: ?string} $data */
    public function handle(User $actor, Inspection $inspection, int $position, array $data): InspectionOverviewBlock
    {
        $this->validatePosition($position);

        return DB::transaction(function () use ($actor, $inspection, $position, $data): InspectionOverviewBlock {
            $locked = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            if ($locked->status->isFinal()) {
                throw ValidationException::withMessages([
                    'comment' => 'A Vista geral não pode ser alterada após o encerramento da inspeção.',
                ]);
            }

            $block = InspectionOverviewBlock::query()->firstOrNew([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $locked->getKey(),
                'position' => $position,
            ]);

            if (! $block->exists) {
                $block->created_by = $actor->getKey();
            }

            $block->fill([
                'comment' => TextNormalizer::nullableText($data['comment'] ?? null),
                'recommendation' => TextNormalizer::nullableText($data['recommendation'] ?? null),
                'updated_by' => $actor->getKey(),
            ])->save();

            return $block->refresh();
        });
    }

    private function validatePosition(int $position): void
    {
        if (! in_array($position, [1, 2], true)) {
            throw ValidationException::withMessages(['position' => 'Bloco de Vista geral inválido.']);
        }
    }
}
