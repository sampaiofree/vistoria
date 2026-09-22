<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Enums\DefectCategory;
use App\Models\DefectAssessment;
use Illuminate\Validation\ValidationException;

final class TelClassificationResolver
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{height_m:string,impact:array{score:int,label:string},risk:array{score:int,color:string},damage_group:array{code:string,label:string},damage_option:array{code:string,label:string,score:int,color:string},tel_score:int,classification:?DefectClassificationDefinition}
     */
    public function resolveTechnical(DefectAssessment $assessment, array $input): array
    {
        if ($assessment->defect->category !== DefectCategory::RoofCladding) {
            throw ValidationException::withMessages([
                'category' => 'A classificação TEL só pode ser aplicada a avarias de Telhado/Tapamento.',
            ]);
        }

        $forbidden = array_values(array_filter(
            [
                'impact_score', 'impact',
                'fall_risk_score', 'risk_score', 'risk',
                'tel_score',
                'classification_score', 'classification_code', 'classification',
            ],
            fn (string $field): bool => array_key_exists($field, $input),
        ));
        if ($forbidden !== []) {
            throw ValidationException::withMessages(array_fill_keys(
                $forbidden,
                'Este valor é calculado pelo sistema e não pode ser informado diretamente.',
            ));
        }

        $height = $input['height_m'] ?? null;
        if (! is_numeric($height) || (float) $height < 0) {
            throw ValidationException::withMessages([
                'height_m' => 'Informe uma altura numérica igual ou superior a zero.',
            ]);
        }

        $heightValue = (float) $height;
        $groupCode = $this->string($input['damage_group_code'] ?? null);
        $optionCode = $this->string($input['damage_option_code'] ?? null);
        $group = NativeDefectCatalog::telDamageGroup($groupCode);
        $option = NativeDefectCatalog::telDamageOption($groupCode, $optionCode);
        $errors = [];

        if ($group === null) {
            $errors['damage_group_code'] = 'Escolha um tipo de dano TEL válido.';
        }
        if ($option === null) {
            $errors['damage_option_code'] = 'Escolha uma condição válida para o tipo de dano TEL informado.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $impactScore = match (true) {
            $heightValue <= 10 => 3,
            $heightValue <= 15 => 4,
            default => 5,
        };
        $riskScore = $option['score'];
        $telScore = $impactScore * $riskScore;

        return [
            'height_m' => $this->normalizedDecimal($height),
            'impact' => [
                'score' => $impactScore,
                'label' => match ($impactScore) {
                    3 => 'Até 10 m, inclusive',
                    4 => 'Acima de 10 m até 15 m, inclusive',
                    5 => 'Acima de 15 m',
                },
            ],
            'risk' => ['score' => $riskScore, 'color' => $option['color']],
            'damage_group' => ['code' => $group['code'], 'label' => $group['label']],
            'damage_option' => $option,
            'tel_score' => $telScore,
            'classification' => NativeDefectCatalog::classifications(DefectCategory::RoofCladding)
                ->first(fn (DefectClassificationDefinition $classification): bool => $classification->contains($telScore)),
        ];
    }

    private function string(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizedDecimal(mixed $value): string
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        return rtrim(rtrim(number_format((float) $normalized, 16, '.', ''), '0'), '.') ?: '0';
    }
}
