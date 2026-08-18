<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Models\DefectClassification;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateDefectClassification
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, DefectClassification $classification, array $data): DefectClassification
    {
        return DB::transaction(function () use ($actor, $classification, $data): DefectClassification {
            $code = TextNormalizer::technicalCode((string) $data['code']);

            if ($code !== $classification->code && $classification->assessments()->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'O código não pode ser alterado depois que a classificação foi utilizada.',
                ]);
            }

            $classification->update([
                'code' => $code,
                'name' => TextNormalizer::text((string) $data['name']),
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'color' => TextNormalizer::hexColor((string) $data['color']),
                'position' => (int) ($data['position'] ?? $classification->position),
                'severity_rank' => isset($data['severity_rank']) ? (int) $data['severity_rank'] : null,
                'updated_by' => $actor->getKey(),
            ]);

            return $classification->refresh();
        });
    }
}
