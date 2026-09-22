<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectCategory;
use App\Enums\MeasurementUnit;
use App\Enums\QuantityCalculationMode;
use App\Enums\QuantityCalculationType;
use App\Enums\StructuralRecoveryElement;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class NativeDefectQuantityCalculator
{
    private const SCALE = 16;

    private const MAX_VALUE = '9999999999999999999999.9999999999999999';

    private const PI = '3.14159265358979323846264338327950288419716939937510';

    /** @return array<string, mixed> */
    public static function rules(DefectCategory $category, mixed $element = null): array
    {
        return match ($category) {
            DefectCategory::Civil => self::rulesForFields(
                ['length', 'height', 'width', 'quantity'],
                ['length', 'height', 'width', 'quantity'],
            ),
            DefectCategory::AnticorrosiveTreatment => self::rulesForFields(['area'], ['area']),
            DefectCategory::StructuralRecovery => self::structuralRecoveryRules($element),
            DefectCategory::RoofCladding => ['quantity' => ['prohibited']],
        };
    }

    /** @return array<string, mixed> */
    public function calculate(DefectCategory $category, array $data): array
    {
        if ($category === DefectCategory::RoofCladding) {
            throw ValidationException::withMessages([
                'quantity' => 'A categoria Telhado/Tapamento não possui quantitativo nesta etapa.',
            ]);
        }

        if ($data === []) {
            throw ValidationException::withMessages([
                'quantity' => 'Informe os campos do quantitativo.',
            ]);
        }

        Validator::make(
            ['quantity' => $data],
            self::rules($category, $data['element'] ?? null),
            attributes: self::attributes(),
        )->validate();

        return match ($category) {
            DefectCategory::Civil => $this->civil($data),
            DefectCategory::AnticorrosiveTreatment => $this->tac($data),
            DefectCategory::StructuralRecovery => $this->structuralRecovery($data),
            DefectCategory::RoofCladding => throw new \LogicException('TEL não possui quantitativo.'),
        };
    }

    /** @return array<string, mixed> */
    private function civil(array $data): array
    {
        $inputs = $this->decimalInputs($data, ['length', 'height', 'width']);
        $quantity = $this->decimal($data, 'quantity');
        $unitValue = $inputs['length']->multipliedBy($inputs['height'])->multipliedBy($inputs['width']);
        $total = $unitValue->multipliedBy($quantity);

        return $this->result(
            DefectCategory::Civil,
            QuantityCalculationType::CivilVolume,
            QuantityCalculationMode::Calculated,
            MeasurementUnit::CubicMeter,
            $inputs,
            $quantity,
            $unitValue,
            $total,
            'Comprimento × Altura × Largura × Quantidade',
        );
    }

    /** @return array<string, mixed> */
    private function tac(array $data): array
    {
        $area = $this->decimal($data, 'area');

        return $this->result(
            DefectCategory::AnticorrosiveTreatment,
            QuantityCalculationType::TacArea,
            QuantityCalculationMode::Manual,
            MeasurementUnit::SquareMeter,
            ['area' => $area],
            BigDecimal::one(),
            null,
            $area,
            'Área total informada manualmente',
        );
    }

    /** @return array<string, mixed> */
    private function structuralRecovery(array $data): array
    {
        $element = StructuralRecoveryElement::from((string) $data['element']);
        $fields = NativeQuantityCatalog::fieldsFor($element);
        $inputs = $this->decimalInputs($data, $fields);

        if ($element->mode() === QuantityCalculationMode::Manual) {
            return $this->result(
                DefectCategory::StructuralRecovery,
                QuantityCalculationType::StructuralRecoveryWeight,
                QuantityCalculationMode::Manual,
                MeasurementUnit::Kilogram,
                ['total_weight' => $inputs['total_weight']],
                BigDecimal::one(),
                null,
                $inputs['total_weight'],
                'Peso total informado manualmente',
                $element,
            );
        }

        $quantity = $this->decimal($data, 'quantity');
        [$unitValue, $formula] = $this->calculatedStructuralRecoveryUnitValue($element, $inputs);

        return $this->result(
            DefectCategory::StructuralRecovery,
            QuantityCalculationType::StructuralRecoveryWeight,
            QuantityCalculationMode::Calculated,
            MeasurementUnit::Kilogram,
            $inputs,
            $quantity,
            $unitValue,
            $unitValue->multipliedBy($quantity),
            $formula,
            $element,
        );
    }

    /** @param array<string, BigDecimal> $v
     * @return array{BigDecimal, string}
     */
    private function calculatedStructuralRecoveryUnitValue(StructuralRecoveryElement $element, array $v): array
    {
        $density = BigDecimal::of(NativeQuantityCatalog::STEEL_DENSITY_KG_M3);
        $two = BigDecimal::of(2);

        return match ($element) {
            StructuralRecoveryElement::ProfileW => (function () use ($v, $density, $two): array {
                $this->ensurePositiveDifference($v['web_height'], $v['web_thickness']->multipliedBy($two), 'web_height');
                $area = $v['flange_width']->multipliedBy($v['flange_thickness'])->multipliedBy($two)
                    ->plus($v['web_height']->minus($v['web_thickness']->multipliedBy($two))->multipliedBy($v['web_thickness']));

                return [$density->multipliedBy($area)->multipliedBy($v['length'])->dividedByExact('1000000'), '7850 × ((M × EM × 2) + ((A − 2 × EA) × EA)) ÷ 1.000.000 × C'];
            })(),
            StructuralRecoveryElement::ProfileL => (function () use ($v, $density): array {
                $this->ensurePositiveDifference($v['width'], $v['thickness'], 'width');
                $area = $v['width']->multipliedBy($v['thickness'])
                    ->plus($v['width']->minus($v['thickness'])->multipliedBy($v['thickness']));

                return [$density->multipliedBy($area)->multipliedBy($v['length'])->dividedByExact('1000000'), '7850 × ((L × E) + ((L − E) × E)) ÷ 1.000.000 × C'];
            })(),
            StructuralRecoveryElement::ProfileU => (function () use ($v, $density, $two): array {
                $this->ensurePositiveDifference($v['width'], $v['web_thickness'], 'width');
                $area = $v['height']->multipliedBy($v['web_thickness'])
                    ->plus($v['width']->minus($v['web_thickness'])->multipliedBy($v['flange_thickness'])->multipliedBy($two));

                return [$density->multipliedBy($area)->multipliedBy($v['length'])->dividedByExact('1000000'), '7850 × ((A × EA) + ((L − EA) × EM × 2)) ÷ 1.000.000 × C'];
            })(),
            StructuralRecoveryElement::ProfileUe => (function () use ($v, $density, $two): array {
                $this->ensurePositiveDifference($v['flange_width'], $v['web_thickness'], 'flange_width');
                $this->ensurePositiveDifference($v['fold_width'], $v['flange_thickness'], 'fold_width');
                $area = $v['height']->multipliedBy($v['web_thickness'])
                    ->plus($v['flange_width']->minus($v['web_thickness'])->multipliedBy($v['flange_thickness'])->multipliedBy($two))
                    ->plus($v['fold_width']->minus($v['flange_thickness'])->multipliedBy($v['flange_thickness'])->multipliedBy($two));

                return [$density->multipliedBy($area)->multipliedBy($v['length'])->dividedByExact('1000000'), '7850 × ((A × EA) + 2 × ((M − EA) × EM) + 2 × ((D − EM) × EM)) ÷ 1.000.000 × C'];
            })(),
            StructuralRecoveryElement::SmoothPlate,
            StructuralRecoveryElement::FlatBar,
            StructuralRecoveryElement::CheckeredPlate => [
                $density->multipliedBy($v['width'])->multipliedBy($v['length'])->multipliedBy($v['thickness'])->dividedByExact('1000000000'),
                '7850 × L × C × E ÷ 1.000.000.000',
            ],
            StructuralRecoveryElement::Guardrail => [$v['length']->multipliedBy('30'), 'C × 30'],
            StructuralRecoveryElement::CagedLadder => [$v['length']->multipliedBy('60'), 'C × 60'],
            StructuralRecoveryElement::TubularProfile => (function () use ($v, $density, $two): array {
                $innerDiameter = $v['outer_diameter']->minus($v['thickness']->multipliedBy($two));
                if (! $innerDiameter->isPositive()) {
                    throw ValidationException::withMessages([
                        'quantity.thickness' => 'A espessura deve resultar em um diâmetro interno maior que zero.',
                    ]);
                }

                $differenceOfSquares = $v['outer_diameter']->multipliedBy($v['outer_diameter'])
                    ->minus($innerDiameter->multipliedBy($innerDiameter));
                $unit = BigDecimal::of(self::PI)
                    ->multipliedBy($differenceOfSquares)
                    ->multipliedBy($v['length'])
                    ->multipliedBy($density)
                    ->dividedByExact('4000000000');

                return [$unit, 'π × (D² − (D − 2 × E)²) ÷ 4 ÷ 1.000.000 × (C ÷ 1000) × 7850'];
            })(),
            StructuralRecoveryElement::UnequalAngle => (function () use ($v, $density): array {
                $this->ensurePositiveDifference($v['leg_2'], $v['thickness'], 'leg_2');
                $area = $v['leg_1']->multipliedBy($v['thickness'])
                    ->plus($v['leg_2']->minus($v['thickness'])->multipliedBy($v['thickness']));

                return [$density->multipliedBy($area)->multipliedBy($v['length'])->dividedByExact('1000000'), '7850 × ((A1 × E) + ((A2 − E) × E)) ÷ 1.000.000 × C'];
            })(),
            StructuralRecoveryElement::Metalon => (function () use ($v, $density, $two): array {
                $this->ensurePositiveDifference($v['side_1'], $v['thickness']->multipliedBy($two), 'side_1');
                $area = $v['side_1']->minus($v['thickness']->multipliedBy($two))->multipliedBy($v['thickness'])->multipliedBy($two)
                    ->plus($v['side_2']->multipliedBy($v['thickness'])->multipliedBy($two));

                return [$density->multipliedBy($area)->multipliedBy($v['length'])->dividedByExact('1000000'), '7850 × (((A1 − 2 × E) × E × 2) + (A2 × E × 2)) × C ÷ 1.000.000'];
            })(),
            StructuralRecoveryElement::TeeProfile => (function () use ($v, $density): array {
                $this->ensurePositiveDifference($v['web_height'], $v['thickness'], 'web_height');
                $area = $v['flange_width']->multipliedBy($v['thickness'])
                    ->plus($v['web_height']->minus($v['thickness'])->multipliedBy($v['thickness']));

                return [$density->multipliedBy($area)->multipliedBy($v['length'])->dividedByExact('1000000'), '7850 × ((M × E) + ((A − E) × E)) ÷ 1.000.000 × C'];
            })(),
            StructuralRecoveryElement::BoltedConnection,
            StructuralRecoveryElement::RoofSheet,
            StructuralRecoveryElement::FloorGrating => throw new \LogicException('Elemento manual não possui fórmula automática.'),
        };
    }

    /** @param array<string, BigDecimal> $inputs
     * @return array<string, mixed>
     */
    private function result(
        DefectCategory $category,
        QuantityCalculationType $type,
        QuantityCalculationMode $mode,
        MeasurementUnit $unit,
        array $inputs,
        BigDecimal $quantity,
        ?BigDecimal $unitValue,
        BigDecimal $total,
        string $formula,
        ?StructuralRecoveryElement $element = null,
    ): array {
        $total = $this->persistable($total, 'O quantitativo total está fora da precisão suportada.');
        $unitValue = $unitValue === null ? null : $this->persistable($unitValue, 'O valor unitário está fora da precisão suportada.');
        $normalizedInputs = array_map(static fn (BigDecimal $value): string => (string) $value, $inputs);
        $normalizedQuantity = (string) $quantity->toScale(self::SCALE);
        $normalizedUnitValue = $unitValue === null ? null : (string) $unitValue;
        $normalizedTotal = (string) $total;
        $snapshot = [
            'source' => 'native_quantity_catalog',
            'formula_version' => NativeQuantityCatalog::VERSION,
            'category' => $category->value,
            'calculation_type' => $type->value,
            'mode' => $mode->value,
            'element' => $element === null ? null : ['code' => $element->value, 'label' => $element->label()],
            'formula' => $formula,
            'density_kg_m3' => $category === DefectCategory::StructuralRecovery && $mode === QuantityCalculationMode::Calculated
                ? NativeQuantityCatalog::STEEL_DENSITY_KG_M3
                : null,
            'inputs' => $normalizedInputs,
            'quantity' => $normalizedQuantity,
            'unit_value' => $normalizedUnitValue,
            'total' => $normalizedTotal,
            'measurement_unit' => $unit->value,
        ];

        return [
            'category' => $category,
            'calculation_type' => $type,
            'rec_element' => $element,
            'inputs' => $normalizedInputs,
            'quantity' => $normalizedQuantity,
            'unit_value' => $normalizedUnitValue,
            'measurement_value' => $normalizedTotal,
            'measurement_unit' => $unit,
            'mode' => $mode,
            'formula_version' => NativeQuantityCatalog::VERSION,
            'formula_snapshot' => $snapshot,
        ];
    }

    /** @param list<string> $allowedFields
     * @param  list<string>  $requiredNumericFields
     * @return array<string, mixed>
     */
    private static function rulesForFields(array $allowedFields, array $requiredNumericFields): array
    {
        $rules = ['quantity' => ['nullable', 'array:'.implode(',', $allowedFields)]];
        foreach ($requiredNumericFields as $field) {
            $rules['quantity.'.$field] = self::positiveDecimalRule();
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    private static function structuralRecoveryRules(mixed $element): array
    {
        $resolved = is_string($element) ? StructuralRecoveryElement::tryFrom($element) : null;
        $fields = $resolved === null ? [] : NativeQuantityCatalog::fieldsFor($resolved);
        $allowed = ['element', ...$fields];
        if ($resolved?->mode() === QuantityCalculationMode::Calculated) {
            $allowed[] = 'quantity';
        }

        $rules = [
            'quantity' => ['nullable', 'array:'.implode(',', $allowed)],
            'quantity.element' => ['required_with:quantity', Rule::enum(StructuralRecoveryElement::class)],
        ];

        foreach ($fields as $field) {
            $rules['quantity.'.$field] = self::positiveDecimalRule();
        }
        if ($resolved?->mode() === QuantityCalculationMode::Calculated) {
            $rules['quantity.quantity'] = self::positiveDecimalRule();
        }

        return $rules;
    }

    /** @return list<string> */
    private static function positiveDecimalRule(): array
    {
        return ['required_with:quantity', 'numeric', 'decimal:0,16', 'gt:0', 'max:999999999999'];
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        return [
            'quantity.element' => 'elemento REC',
            'quantity.length' => 'comprimento',
            'quantity.height' => 'altura',
            'quantity.width' => 'largura',
            'quantity.quantity' => 'quantidade',
            'quantity.area' => 'área',
            'quantity.flange_width' => 'mesa',
            'quantity.flange_thickness' => 'espessura da mesa',
            'quantity.web_height' => 'alma',
            'quantity.web_thickness' => 'espessura da alma',
            'quantity.thickness' => 'espessura',
            'quantity.fold_width' => 'dobra da mesa',
            'quantity.outer_diameter' => 'diâmetro externo',
            'quantity.leg_1' => 'aba 1',
            'quantity.leg_2' => 'aba 2',
            'quantity.side_1' => 'aba 1',
            'quantity.side_2' => 'aba 2',
            'quantity.total_weight' => 'peso total',
        ];
    }

    /** @param list<string> $fields
     * @return array<string, BigDecimal>
     */
    private function decimalInputs(array $data, array $fields): array
    {
        $inputs = [];
        foreach ($fields as $field) {
            $inputs[$field] = $this->decimal($data, $field);
        }

        return $inputs;
    }

    private function decimal(array $data, string $field): BigDecimal
    {
        return BigDecimal::of((string) $data[$field]);
    }

    private function ensurePositiveDifference(BigDecimal $minuend, BigDecimal $subtrahend, string $field): void
    {
        if (! $minuend->minus($subtrahend)->isPositive()) {
            throw ValidationException::withMessages([
                'quantity.'.$field => 'As dimensões informadas resultam em uma geometria impossível.',
            ]);
        }
    }

    private function persistable(BigDecimal $value, string $message): BigDecimal
    {
        $rounded = $value->toScale(self::SCALE, RoundingMode::HalfUp);
        if (! $rounded->isPositive() || $rounded->isGreaterThan(self::MAX_VALUE)) {
            throw ValidationException::withMessages(['quantity' => $message]);
        }

        return $rounded;
    }
}
