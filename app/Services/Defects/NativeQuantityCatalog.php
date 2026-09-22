<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectCategory;
use App\Enums\QuantityCalculationMode;
use App\Enums\QuantityCalculationType;
use App\Enums\StructuralRecoveryElement;

final class NativeQuantityCatalog
{
    public const VERSION = 1;

    public const STEEL_DENSITY_KG_M3 = '7850';

    /** @var array<string, array{label:string,unit:string}> */
    private const FIELDS = [
        'length' => ['label' => 'Comprimento', 'unit' => 'm'],
        'height' => ['label' => 'Altura', 'unit' => 'm'],
        'width' => ['label' => 'Largura', 'unit' => 'm'],
        'quantity' => ['label' => 'Quantidade', 'unit' => 'unit'],
        'area' => ['label' => 'Área', 'unit' => 'm2'],
        'flange_width' => ['label' => 'Mesa', 'unit' => 'mm'],
        'flange_thickness' => ['label' => 'Espessura da mesa', 'unit' => 'mm'],
        'web_height' => ['label' => 'Alma', 'unit' => 'mm'],
        'web_thickness' => ['label' => 'Espessura da alma', 'unit' => 'mm'],
        'thickness' => ['label' => 'Espessura', 'unit' => 'mm'],
        'fold_width' => ['label' => 'Dobra da mesa', 'unit' => 'mm'],
        'outer_diameter' => ['label' => 'Diâmetro externo', 'unit' => 'mm'],
        'leg_1' => ['label' => 'Aba 1', 'unit' => 'mm'],
        'leg_2' => ['label' => 'Aba 2', 'unit' => 'mm'],
        'side_1' => ['label' => 'Aba 1', 'unit' => 'mm'],
        'side_2' => ['label' => 'Aba 2', 'unit' => 'mm'],
        'total_weight' => ['label' => 'Peso total', 'unit' => 'kg'],
    ];

    /** @var array<string, list<string>> */
    private const REC_FIELDS = [
        'profile_w' => ['flange_width', 'flange_thickness', 'web_height', 'web_thickness', 'length'],
        'profile_l' => ['width', 'thickness', 'length'],
        'profile_u' => ['height', 'web_thickness', 'width', 'flange_thickness', 'length'],
        'profile_ue' => ['height', 'web_thickness', 'flange_width', 'fold_width', 'flange_thickness', 'length'],
        'smooth_plate' => ['width', 'length', 'thickness'],
        'flat_bar' => ['width', 'length', 'thickness'],
        'checkered_plate' => ['width', 'length', 'thickness'],
        'guardrail' => ['length'],
        'caged_ladder' => ['length'],
        'tubular_profile' => ['outer_diameter', 'thickness', 'length'],
        'unequal_angle' => ['leg_1', 'leg_2', 'thickness', 'length'],
        'metalon' => ['side_1', 'side_2', 'thickness', 'length'],
        'tee_profile' => ['flange_width', 'web_height', 'thickness', 'length'],
        'bolted_connection' => ['total_weight'],
        'roof_sheet' => ['total_weight'],
        'floor_grating' => ['total_weight'],
    ];

    /** @return list<string> */
    public static function fieldsFor(StructuralRecoveryElement $element): array
    {
        return self::REC_FIELDS[$element->value];
    }

    /** @return array<string, mixed> */
    public static function definition(DefectCategory $category): array
    {
        return match ($category) {
            DefectCategory::Civil => [
                'category' => $category->value,
                'calculation_type' => QuantityCalculationType::CivilVolume->value,
                'mode' => QuantityCalculationMode::Calculated->value,
                'unit' => 'm3',
                'fields' => self::fieldDefinitions(['length', 'height', 'width', 'quantity']),
            ],
            DefectCategory::AnticorrosiveTreatment => [
                'category' => $category->value,
                'calculation_type' => QuantityCalculationType::TacArea->value,
                'mode' => QuantityCalculationMode::Manual->value,
                'unit' => 'm2',
                'fields' => self::fieldDefinitions(['area']),
            ],
            DefectCategory::StructuralRecovery => [
                'category' => $category->value,
                'calculation_type' => QuantityCalculationType::StructuralRecoveryWeight->value,
                'unit' => 'kg',
                'density_kg_m3' => self::STEEL_DENSITY_KG_M3,
                'elements' => array_map(
                    fn (StructuralRecoveryElement $element): array => [
                        'code' => $element->value,
                        'label' => $element->label(),
                        'mode' => $element->mode()->value,
                        'fields' => self::fieldDefinitions([
                            ...self::fieldsFor($element),
                            ...($element->mode() === QuantityCalculationMode::Calculated ? ['quantity'] : []),
                        ], self::unitsFor($element)),
                    ],
                    StructuralRecoveryElement::cases(),
                ),
            ],
            DefectCategory::RoofCladding => [
                'category' => $category->value,
                'available' => false,
                'mode' => null,
                'unit' => null,
                'fields' => [],
            ],
        };
    }

    /** @param list<string> $fields
     * @return list<array{key:string,label:string,unit:string}>
     */
    private static function fieldDefinitions(array $fields, array $unitOverrides = []): array
    {
        return array_map(
            fn (string $field): array => [
                'key' => $field,
                ...self::FIELDS[$field],
                'unit' => $unitOverrides[$field] ?? self::FIELDS[$field]['unit'],
            ],
            $fields,
        );
    }

    /** @return array<string, string> */
    private static function unitsFor(StructuralRecoveryElement $element): array
    {
        $units = array_fill_keys(self::fieldsFor($element), 'mm');

        if (in_array($element, [
            StructuralRecoveryElement::ProfileW,
            StructuralRecoveryElement::ProfileL,
            StructuralRecoveryElement::ProfileU,
            StructuralRecoveryElement::ProfileUe,
            StructuralRecoveryElement::Guardrail,
            StructuralRecoveryElement::CagedLadder,
            StructuralRecoveryElement::UnequalAngle,
            StructuralRecoveryElement::Metalon,
            StructuralRecoveryElement::TeeProfile,
        ], true)) {
            $units['length'] = 'm';
        }

        if ($element->mode() === QuantityCalculationMode::Manual) {
            $units['total_weight'] = 'kg';
        }

        return $units;
    }
}
