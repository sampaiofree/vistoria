<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Enums\DefectCategory;
use App\Enums\GutCriterion;
use App\Models\DefectAssessment;
use Illuminate\Validation\ValidationException;

final class GutClassificationResolver
{
    /**
     * @param  array{gravity:mixed,urgency:mixed,trend:mixed}  $scores
     * @return array{classification:?DefectClassificationDefinition,gut_score:int,criteria:array<string,array{score:int,color:string}>}
     */
    public function resolve(DefectCategory $category, array $scores): array
    {
        $options = NativeDefectCatalog::gutOptions();
        $criteria = [];
        $errors = [];

        foreach (GutCriterion::cases() as $criterion) {
            $score = $scores[$criterion->value] ?? null;
            $note = is_int($score) || is_string($score) || is_float($score)
                ? filter_var($score, FILTER_VALIDATE_INT)
                : false;
            $allowedMaximum = $category === DefectCategory::AnticorrosiveTreatment
                && $criterion === GutCriterion::Gravity
                ? 3
                : 5;
            $option = $note === false || $note < 1 || $note > $allowedMaximum
                ? null
                : collect($options[$criterion->value])->firstWhere('score', $note);

            if ($option === null) {
                $errors[$criterion->value] = $allowedMaximum === 3
                    ? 'A Gravidade TAC deve estar entre 1 e 3.'
                    : 'Escolha uma nota GUT de 1 a 5 para este critério.';

                continue;
            }

            $criteria[$criterion->value] = $option;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $gutScore = $criteria['gravity']['score'] * $criteria['urgency']['score'] * $criteria['trend']['score'];

        return [
            'classification' => NativeDefectCatalog::classifications($category)
                ->first(fn (DefectClassificationDefinition $classification): bool => $classification->contains($gutScore)),
            'gut_score' => $gutScore,
            'criteria' => $criteria,
        ];
    }

    /**
     * Resolve the inspector's technical choices and derived source fields.
     *
     * @param  array<string, mixed>  $input
     * @return array{classification:?DefectClassificationDefinition,gut_score:int,criteria:array<string,array<string,mixed>>}
     */
    public function resolveTechnical(DefectAssessment $assessment, array $input): array
    {
        $forbiddenScores = array_values(array_filter(
            ['gravity', 'urgency', 'trend'],
            fn (string $field): bool => array_key_exists($field, $input),
        ));

        if ($forbiddenScores !== []) {
            throw ValidationException::withMessages(array_fill_keys(
                $forbiddenScores,
                'Esta nota é calculada pelo sistema e não pode ser informada diretamente.',
            ));
        }

        $assessment->loadMissing(['defect', 'inspection.equipment']);
        $category = $assessment->defect->category;
        $criteria = match ($category) {
            DefectCategory::Civil => $this->resolveCivil($input),
            DefectCategory::StructuralRecovery => $this->resolveStructuralRecovery($input),
            DefectCategory::AnticorrosiveTreatment => $this->resolveAnticorrosiveTreatment($assessment, $input),
        };

        $resolved = $this->resolve($category, [
            GutCriterion::Gravity->value => $criteria[GutCriterion::Gravity->value]['score'],
            GutCriterion::Urgency->value => $criteria[GutCriterion::Urgency->value]['score'],
            GutCriterion::Trend->value => $criteria[GutCriterion::Trend->value]['score'],
        ]);

        return [
            'classification' => $resolved['classification'],
            'gut_score' => $resolved['gut_score'],
            'criteria' => $criteria,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, array<string, mixed>> */
    private function resolveCivil(array $input): array
    {
        $gravity = $this->resolveImpactGravity($input);
        $urgency = $this->resolveCatalogOrManualUrgency(DefectCategory::Civil, $input);
        $group = NativeDefectCatalog::trendGroup(DefectCategory::Civil, $this->string($input, 'trend_group_code'));

        if ($group === null) {
            throw ValidationException::withMessages([
                'trend_group_code' => 'Escolha um tipo de degradação CIVIL válido.',
            ]);
        }

        $trend = $this->manualCriterion(
            input: $input,
            descriptionField: 'trend_manual_description',
            scoreField: 'trend_manual_score',
            mode: 'manual',
        );
        $trend['group'] = ['code' => $group['code'], 'label' => $group['label']];

        return [
            GutCriterion::Gravity->value => $gravity,
            GutCriterion::Urgency->value => $urgency,
            GutCriterion::Trend->value => $trend,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, array<string, mixed>> */
    private function resolveStructuralRecovery(array $input): array
    {
        $gravity = $this->resolveImpactGravity($input);
        $urgency = $this->resolveCatalogOrManualUrgency(DefectCategory::StructuralRecovery, $input);
        $group = NativeDefectCatalog::trendGroup(DefectCategory::StructuralRecovery, $this->string($input, 'trend_group_code'));

        if ($group === null) {
            throw ValidationException::withMessages([
                'trend_group_code' => 'Escolha um dano REC válido.',
            ]);
        }

        $option = NativeDefectCatalog::trend(
            DefectCategory::StructuralRecovery,
            $group['code'],
            $this->string($input, 'trend_option_code'),
        );

        if ($option === null) {
            throw ValidationException::withMessages([
                'trend_option_code' => 'Escolha uma condição válida para o dano REC informado.',
            ]);
        }

        return [
            GutCriterion::Gravity->value => $gravity,
            GutCriterion::Urgency->value => $urgency,
            GutCriterion::Trend->value => [
                'score' => $option['score'],
                'color' => $option['color'],
                'mode' => 'catalog',
                'group' => ['code' => $group['code'], 'label' => $group['label']],
                'option' => $option,
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, array<string, mixed>> */
    private function resolveAnticorrosiveTreatment(DefectAssessment $assessment, array $input): array
    {
        $definition = NativeDefectCatalog::technicalDefinition(
            DefectCategory::AnticorrosiveTreatment,
            $assessment->inspection->equipment->abc_code,
            $assessment->inspection->atmospheric_classification,
        );
        $gravitySource = $definition['sources']['gravity'];
        $urgencySource = $definition['sources']['urgency'];
        $errors = [];

        if (! $gravitySource['valid']) {
            $errors['equipment.abc_code'] = $gravitySource['message'];
        }
        if (! $urgencySource['valid']) {
            $errors['inspection.atmospheric_classification'] = $urgencySource['message'];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $trend = NativeDefectCatalog::trend(
            DefectCategory::AnticorrosiveTreatment,
            null,
            $this->string($input, 'trend_option_code'),
        );
        if ($trend === null) {
            throw ValidationException::withMessages([
                'trend_option_code' => 'Escolha um grau de oxidação ASTM D610 válido.',
            ]);
        }

        return [
            GutCriterion::Gravity->value => [
                'score' => $gravitySource['score'],
                'color' => $gravitySource['color'],
                'mode' => 'derived',
                'source' => collect($gravitySource)->except(['valid', 'message', 'mapping'])->all(),
            ],
            GutCriterion::Urgency->value => [
                'score' => $urgencySource['score'],
                'color' => $urgencySource['color'],
                'mode' => 'derived',
                'source' => collect($urgencySource)->except(['valid', 'message', 'mapping'])->all(),
            ],
            GutCriterion::Trend->value => [
                'score' => $trend['score'],
                'color' => $trend['color'],
                'mode' => 'catalog',
                'option' => $trend,
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function resolveImpactGravity(array $input): array
    {
        $safety = NativeDefectCatalog::safetyImpact($this->string($input, 'safety_impact_code'));
        $asset = NativeDefectCatalog::assetImpact($this->string($input, 'asset_impact_code'));
        $errors = [];

        if ($safety === null) {
            $errors['safety_impact_code'] = 'Escolha um impacto na segurança válido.';
        }
        if ($asset === null) {
            $errors['asset_impact_code'] = 'Escolha um impacto no ativo válido.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $score = max($safety['score'], $asset['score']);

        return [
            'score' => $score,
            'color' => NativeDefectCatalog::colorForScore($score),
            'mode' => 'calculated',
            'calculation' => 'maximum',
            'safety_impact' => $safety,
            'asset_impact' => $asset,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function resolveCatalogOrManualUrgency(DefectCategory $category, array $input): array
    {
        $optionCode = $this->string($input, 'urgency_option_code');
        $manualDescription = $this->string($input, 'urgency_manual_description');
        $manualScore = $input['urgency_manual_score'] ?? null;

        if ($optionCode !== null) {
            if ($manualDescription !== null || $manualScore !== null) {
                throw ValidationException::withMessages([
                    'urgency_manual_description' => 'Use a opção de catálogo ou o preenchimento manual, nunca os dois.',
                    'urgency_manual_score' => 'Use a opção de catálogo ou o preenchimento manual, nunca os dois.',
                ]);
            }

            $option = NativeDefectCatalog::urgency($category, $optionCode);
            if ($option === null) {
                throw ValidationException::withMessages([
                    'urgency_option_code' => 'Escolha uma função ou criticidade válida para esta categoria.',
                ]);
            }

            return [
                'score' => $option['score'],
                'color' => $option['color'],
                'mode' => 'catalog',
                'option' => $option,
            ];
        }

        if ($category !== DefectCategory::StructuralRecovery) {
            throw ValidationException::withMessages([
                'urgency_option_code' => 'Escolha uma função ou criticidade válida para esta categoria.',
            ]);
        }

        return $this->manualCriterion(
            input: $input,
            descriptionField: 'urgency_manual_description',
            scoreField: 'urgency_manual_score',
            mode: 'manual',
        );
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function manualCriterion(array $input, string $descriptionField, string $scoreField, string $mode): array
    {
        $description = $this->string($input, $descriptionField);
        $rawScore = $input[$scoreField] ?? null;
        $score = is_int($rawScore) || is_string($rawScore)
            ? filter_var($rawScore, FILTER_VALIDATE_INT)
            : false;
        $errors = [];

        if ($description === null) {
            $errors[$descriptionField] = 'Informe a descrição técnica utilizada nesta avaliação.';
        }
        if ($score === false || $score < 1 || $score > 5) {
            $errors[$scoreField] = 'Informe uma nota entre 1 e 5.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'score' => $score,
            'color' => NativeDefectCatalog::colorForScore($score),
            'mode' => $mode,
            'manual' => [
                'description' => $description,
                'score' => $score,
                'color' => NativeDefectCatalog::colorForScore($score),
            ],
        ];
    }

    /** @param array<string, mixed> $input */
    private function string(array $input, string $key): ?string
    {
        $value = trim((string) ($input[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
