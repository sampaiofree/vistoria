<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DefectCategory;
use App\Enums\MeasurementUnit;
use App\Enums\QuantityCalculationMode;
use App\Enums\StructuralRecoveryElement;
use App\Services\Defects\NativeDefectQuantityCalculator;
use App\Services\Defects\NativeQuantityCatalog;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NativeDefectQuantityCalculatorTest extends TestCase
{
    #[DataProvider('calculatedStructuralRecoveryCases')]
    public function test_it_calculates_every_structural_recovery_formula(
        string $element,
        array $inputs,
        string $expectedUnit,
        string $expectedTotal,
    ): void {
        $result = app(NativeDefectQuantityCalculator::class)->calculate(
            DefectCategory::StructuralRecovery,
            ['element' => $element, ...$inputs],
        );

        $this->assertSame($expectedUnit, $result['unit_value']);
        $this->assertSame($expectedTotal, $result['measurement_value']);
        $this->assertSame(MeasurementUnit::Kilogram, $result['measurement_unit']);
        $this->assertSame(QuantityCalculationMode::Calculated, $result['mode']);
        $this->assertSame($element, $result['rec_element']->value);
        $this->assertSame(NativeQuantityCatalog::STEEL_DENSITY_KG_M3, $result['formula_snapshot']['density_kg_m3']);
    }

    public static function calculatedStructuralRecoveryCases(): array
    {
        return [
            'perfil W' => ['profile_w', ['flange_width' => 200, 'flange_thickness' => 10, 'web_height' => 300, 'web_thickness' => 8, 'length' => 2, 'quantity' => 2], '98.4704000000000000', '196.9408000000000000'],
            'perfil L' => ['profile_l', ['width' => 76, 'thickness' => 6, 'length' => 2.8, 'quantity' => 2], '19.2544800000000000', '38.5089600000000000'],
            'perfil U' => ['profile_u', ['height' => 200, 'web_thickness' => 8, 'width' => 75, 'flange_thickness' => 10, 'length' => 3, 'quantity' => 2], '69.2370000000000000', '138.4740000000000000'],
            'perfil UE' => ['profile_ue', ['height' => 200, 'web_thickness' => 8, 'flange_width' => 75, 'fold_width' => 20, 'flange_thickness' => 6, 'length' => 3, 'quantity' => 2], '60.5706000000000000', '121.1412000000000000'],
            'chapa lisa' => ['smooth_plate', ['width' => 1000, 'length' => 2000, 'thickness' => 10, 'quantity' => 2], '157.0000000000000000', '314.0000000000000000'],
            'barra chata' => ['flat_bar', ['width' => 1000, 'length' => 2000, 'thickness' => 10, 'quantity' => 2], '157.0000000000000000', '314.0000000000000000'],
            'chapa xadrez' => ['checkered_plate', ['width' => 1000, 'length' => 2000, 'thickness' => 10, 'quantity' => 2], '157.0000000000000000', '314.0000000000000000'],
            'guarda-corpo' => ['guardrail', ['length' => 2.5, 'quantity' => 2], '75.0000000000000000', '150.0000000000000000'],
            'escada marinheiro' => ['caged_ladder', ['length' => 2.5, 'quantity' => 2], '150.0000000000000000', '300.0000000000000000'],
            'perfil tubular' => ['tubular_profile', ['outer_diameter' => 100, 'thickness' => 5, 'length' => 2000, 'quantity' => 2], '23.4284272141458831', '46.8568544282917662'],
            'perfil L desiguais' => ['unequal_angle', ['leg_1' => 75, 'leg_2' => 50, 'thickness' => 6, 'length' => 2, 'quantity' => 2], '11.2098000000000000', '22.4196000000000000'],
            'metalon' => ['metalon', ['side_1' => 100, 'side_2' => 50, 'thickness' => 4, 'length' => 2, 'quantity' => 2], '17.8352000000000000', '35.6704000000000000'],
            'perfil T' => ['tee_profile', ['flange_width' => 100, 'web_height' => 150, 'thickness' => 8, 'length' => 2, 'quantity' => 2], '30.3952000000000000', '60.7904000000000000'],
        ];
    }

    #[DataProvider('manualStructuralRecoveryCases')]
    public function test_it_accepts_only_manual_total_weight_for_manual_elements(string $element): void
    {
        $result = app(NativeDefectQuantityCalculator::class)->calculate(
            DefectCategory::StructuralRecovery,
            ['element' => $element, 'total_weight' => '12.3456789012345678'],
        );

        $this->assertNull($result['unit_value']);
        $this->assertSame('12.3456789012345678', $result['measurement_value']);
        $this->assertSame('1.0000000000000000', $result['quantity']);
        $this->assertSame(QuantityCalculationMode::Manual, $result['mode']);
        $this->assertSame('Peso total informado manualmente', $result['formula_snapshot']['formula']);
    }

    public static function manualStructuralRecoveryCases(): array
    {
        return array_map(
            static fn (StructuralRecoveryElement $element): array => [$element->value],
            [
                StructuralRecoveryElement::BoltedConnection,
                StructuralRecoveryElement::RoofSheet,
                StructuralRecoveryElement::FloorGrating,
            ],
        );
    }

    #[DataProvider('invalidGeometryCases')]
    public function test_it_rejects_impossible_structural_geometry(array $input, string $error): void
    {
        try {
            app(NativeDefectQuantityCalculator::class)->calculate(DefectCategory::StructuralRecovery, $input);
            $this->fail('A geometria inválida deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($error, $exception->errors());
        }
    }

    public static function invalidGeometryCases(): array
    {
        return [
            'W sem alma interna' => [['element' => 'profile_w', 'flange_width' => 200, 'flange_thickness' => 10, 'web_height' => 16, 'web_thickness' => 8, 'length' => 2, 'quantity' => 1], 'quantity.web_height'],
            'L sem largura útil' => [['element' => 'profile_l', 'width' => 6, 'thickness' => 6, 'length' => 2, 'quantity' => 1], 'quantity.width'],
            'U sem mesa útil' => [['element' => 'profile_u', 'height' => 200, 'web_thickness' => 8, 'width' => 8, 'flange_thickness' => 10, 'length' => 2, 'quantity' => 1], 'quantity.width'],
            'UE sem dobra útil' => [['element' => 'profile_ue', 'height' => 200, 'web_thickness' => 8, 'flange_width' => 75, 'fold_width' => 6, 'flange_thickness' => 6, 'length' => 2, 'quantity' => 1], 'quantity.fold_width'],
            'tubo sem diâmetro interno' => [['element' => 'tubular_profile', 'outer_diameter' => 10, 'thickness' => 5, 'length' => 1000, 'quantity' => 1], 'quantity.thickness'],
            'L desigual sem aba útil' => [['element' => 'unequal_angle', 'leg_1' => 20, 'leg_2' => 5, 'thickness' => 5, 'length' => 2, 'quantity' => 1], 'quantity.leg_2'],
            'metalon sem vão interno' => [['element' => 'metalon', 'side_1' => 8, 'side_2' => 20, 'thickness' => 4, 'length' => 2, 'quantity' => 1], 'quantity.side_1'],
            'T sem alma útil' => [['element' => 'tee_profile', 'flange_width' => 20, 'web_height' => 5, 'thickness' => 5, 'length' => 2, 'quantity' => 1], 'quantity.web_height'],
        ];
    }

    public function test_catalog_exposes_stable_codes_modes_fields_and_units(): void
    {
        $definition = NativeQuantityCatalog::definition(DefectCategory::StructuralRecovery);
        $elements = collect($definition['elements'])->keyBy('code');

        $this->assertSame(16, $elements->count());
        $this->assertSame('calculated', $elements['profile_w']['mode']);
        $this->assertSame('manual', $elements['bolted_connection']['mode']);
        $this->assertSame('m', collect($elements['profile_w']['fields'])->firstWhere('key', 'length')['unit']);
        $this->assertSame('mm', collect($elements['smooth_plate']['fields'])->firstWhere('key', 'length')['unit']);
        $this->assertSame('mm', collect($elements['profile_u']['fields'])->firstWhere('key', 'height')['unit']);
        $this->assertSame('kg', collect($elements['floor_grating']['fields'])->firstWhere('key', 'total_weight')['unit']);
    }
}
