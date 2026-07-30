<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectCategory;
use App\Models\DefectCodeSequence;
use App\Models\Equipment;
use App\Support\TextNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DefectCodeGenerator
{
    /**
     * @return array{number:int, code:string}
     */
    public function next(Equipment $equipment, DefectCategory $category): array
    {
        $prefix = TextNormalizer::technicalCode($equipment->defect_code_prefix);

        if ($prefix === null) {
            throw ValidationException::withMessages([
                'defect_code_prefix' => 'Configure o prefixo de avaria do equipamento antes de criar avarias.',
            ]);
        }

        return DB::transaction(function () use ($equipment, $category, $prefix): array {
            $sequence = DefectCodeSequence::query()
                ->where('organization_id', $equipment->organization_id)
                ->where('equipment_id', $equipment->getKey())
                ->where('category', $category->value)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                try {
                    $sequence = DefectCodeSequence::query()->create([
                        'organization_id' => $equipment->organization_id,
                        'equipment_id' => $equipment->getKey(),
                        'category' => $category,
                        'last_number' => 0,
                    ]);
                } catch (QueryException) {
                    $sequence = null;
                }

                $sequence = DefectCodeSequence::query()
                    ->where('organization_id', $equipment->organization_id)
                    ->where('equipment_id', $equipment->getKey())
                    ->where('category', $category->value)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $nextNumber = $sequence->last_number + 1;

            $sequence->update([
                'last_number' => $nextNumber,
            ]);

            return [
                'number' => $nextNumber,
                'code' => sprintf('%s-%s-%03d', $prefix, $category->code(), $nextNumber),
            ];
        });
    }
}
