<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Enums\AssetAbcClass;
use App\Enums\AtmosphericCorrosivity;
use App\Enums\DefectCategory;
use App\Enums\GutCriterion;
use Illuminate\Support\Collection;

final class NativeDefectCatalog
{
    public const VERSION = 2;

    /** @var array<string, list<array{code:string,name:string,color:string,position:int,lower_limit:int,upper_limit:int,description:string}>> */
    private const CLASSIFICATIONS = [
        'CV' => [
            ['code' => 'CV-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 75, 'upper_limit' => 125, 'description' => 'Tratar em até 1 ano'],
            ['code' => 'CV-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 36, 'upper_limit' => 74, 'description' => 'Tratar em até 2 anos'],
            ['code' => 'CV-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 16, 'upper_limit' => 35, 'description' => 'Tratar em até 3 anos'],
            ['code' => 'CV-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 8, 'upper_limit' => 15, 'description' => 'Intervenção por oportunidade'],
            ['code' => 'CV-5', 'name' => 'Muito baixa', 'color' => '#0070C0', 'position' => 5, 'lower_limit' => 1, 'upper_limit' => 7, 'description' => 'Registro de condição'],
        ],
        'TAC' => [
            ['code' => 'TA-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 45, 'upper_limit' => 75, 'description' => 'Tratar em até 1 ano'],
            ['code' => 'TA-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 25, 'upper_limit' => 44, 'description' => 'Tratar em até 3 anos'],
            ['code' => 'TA-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 15, 'upper_limit' => 24, 'description' => 'Tratar em até 5 anos'],
            ['code' => 'TA-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 9, 'upper_limit' => 14, 'description' => 'Intervenção por oportunidade'],
            ['code' => 'TA-5', 'name' => 'Muito baixa', 'color' => '#0070C0', 'position' => 5, 'lower_limit' => 3, 'upper_limit' => 8, 'description' => 'Registro de condição'],
        ],
        'REC' => [
            ['code' => 'IE-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 75, 'upper_limit' => 125, 'description' => 'Tratar em até 1 ano'],
            ['code' => 'IE-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 36, 'upper_limit' => 74, 'description' => 'Tratar em até 2 anos'],
            ['code' => 'IE-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 16, 'upper_limit' => 35, 'description' => 'Tratar em até 3 anos'],
            ['code' => 'IE-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 8, 'upper_limit' => 15, 'description' => 'Intervenção por oportunidade'],
            ['code' => 'IE-5', 'name' => 'Muito baixa', 'color' => '#0070C0', 'position' => 5, 'lower_limit' => 1, 'upper_limit' => 7, 'description' => 'Registro de condição'],
        ],
    ];

    /** @var array<int, string> */
    private const GUT_COLORS = [
        1 => '#00AEEF',
        2 => '#92D050',
        3 => '#FFFF00',
        4 => '#FFC000',
        5 => '#FF0000',
    ];

    /** @var list<array{code:string,label:string,score:int}> */
    private const SAFETY_IMPACTS = [
        ['code' => 'no_accident_risk', 'label' => 'Sem possibilidade de acidente', 'score' => 1],
        ['code' => 'secondary_up_to_2m', 'label' => 'Dano em elemento estrutural secundário, com possibilidade de acidente até 2 m', 'score' => 2],
        ['code' => 'secondary_above_2m', 'label' => 'Dano em elemento estrutural secundário, com possibilidade de acidente acima de 2 m', 'score' => 3],
        ['code' => 'primary_up_to_2m', 'label' => 'Dano em elemento estrutural primário, com possibilidade de acidente até 2 m', 'score' => 4],
        ['code' => 'primary_above_2m', 'label' => 'Dano em elemento estrutural primário, com possibilidade de acidente acima de 2 m', 'score' => 5],
    ];

    /** @var list<array{code:string,label:string,score:int}> */
    private const ASSET_IMPACTS = [
        ['code' => 'secondary_without_asset_impact', 'label' => 'Dano ou ausência de elemento estrutural secundário que não impacta o ativo', 'score' => 1],
        ['code' => 'primary_local_low_criticality', 'label' => 'Dano pontual em elemento estrutural primário de ativos de baixa criticidade (C e D)', 'score' => 2],
        ['code' => 'primary_local_high_criticality', 'label' => 'Dano pontual em elemento estrutural primário de ativos de alta criticidade (A e B)', 'score' => 3],
        ['code' => 'primary_general_low_criticality', 'label' => 'Dano generalizado ou ausência de elemento estrutural primário de ativos de baixa criticidade (C e D)', 'score' => 4],
        ['code' => 'primary_general_high_criticality', 'label' => 'Dano generalizado ou ausência de elemento estrutural primário de ativos de alta criticidade (A e B)', 'score' => 5],
    ];

    /** @var list<array{code:string,label:string,score:int}> */
    private const CIVIL_URGENCY = [
        ['code' => 'non_structural_masonry_wall', 'label' => 'Paredes de alvenaria sem finalidade estrutural', 'score' => 1],
        ['code' => 'anchor_without_safety_relevance', 'label' => 'Ligações chumbadas sem relevância à segurança, como fixação de guarda-corpos', 'score' => 1],
        ['code' => 'concrete_guardrail', 'label' => 'Guarda-corpos de concreto', 'score' => 2],
        ['code' => 'roof_support_structure', 'label' => 'Estruturas de sustentação de telhados/coberturas', 'score' => 2],
        ['code' => 'anchor_with_falling_object_risk', 'label' => 'Ligações chumbadas cuja falha possibilite queda de objetos, como fixação de monovias', 'score' => 2],
        ['code' => 'stair_structure', 'label' => 'Estruturas de escadas', 'score' => 3],
        ['code' => 'essential_component_anchor', 'label' => 'Ligações chumbadas de componentes essenciais', 'score' => 3],
        ['code' => 'grade_beam', 'label' => 'Vigas baldrame', 'score' => 3],
        ['code' => 'pile_cap', 'label' => 'Blocos de coroamento', 'score' => 3],
        ['code' => 'primary_structure_auxiliary', 'label' => 'Estruturas auxiliares de estruturas primárias, como mísulas', 'score' => 3],
        ['code' => 'slab_without_people_access', 'label' => 'Lajes sem acesso de pessoas', 'score' => 3],
        ['code' => 'soil_reinforcement', 'label' => 'Estruturas de reforço do solo, como terra armada', 'score' => 3],
        ['code' => 'component_fixing_structure', 'label' => 'Estrutura de fixação de componentes', 'score' => 3],
        ['code' => 'running_rail', 'label' => 'Trilhos, quando aplicável ao caminho de rolamento', 'score' => 3],
        ['code' => 'secondary_elevation_beam', 'label' => 'Vigas secundárias de elevações', 'score' => 4],
        ['code' => 'equipment_support_column', 'label' => 'Colunas de sustentação de equipamentos', 'score' => 4],
        ['code' => 'equipment_support_beam', 'label' => 'Vigas de sustentação de equipamentos', 'score' => 4],
        ['code' => 'equipment_support_base', 'label' => 'Bases de sustentação de equipamentos', 'score' => 4],
        ['code' => 'slab_with_people_access', 'label' => 'Lajes com acesso de pessoas', 'score' => 4],
        ['code' => 'foundation', 'label' => 'Fundações em geral', 'score' => 4],
        ['code' => 'soil_containment', 'label' => 'Estruturas de contenção do solo, como muros de arrimo e cortinas atirantadas', 'score' => 4],
        ['code' => 'tank_silo_chimney_shell', 'label' => 'Costado de tanques, silos e chaminés', 'score' => 4],
        ['code' => 'rail_joint', 'label' => 'Emendas do trilho, quando aplicável ao caminho de rolamento', 'score' => 4],
        ['code' => 'building_support_column', 'label' => 'Colunas de sustentação de edifícios', 'score' => 5],
        ['code' => 'main_elevation_beam', 'label' => 'Vigas principais de elevações', 'score' => 5],
        ['code' => 'gantry_or_crane_beam', 'label' => 'Pórticos e vigas de ponte rolante', 'score' => 5],
        ['code' => 'cantilever_tie_or_bracket', 'label' => 'Tirantes e mão-francesa de estruturas em balanço', 'score' => 5],
    ];

    /** @var list<array{code:string,label:string,score:int}> */
    private const REC_URGENCY = [
        ['code' => 'guardrail_or_stair_mesh', 'label' => 'Telas de guarda-corpo e de escadas', 'score' => 1],
        ['code' => 'guardrail_or_stair_toeboard', 'label' => 'Rodapé de guarda-corpo e de escadas', 'score' => 1],
        ['code' => 'ladder_mesh', 'label' => 'Grade de escada de marinheiro', 'score' => 1],
        ['code' => 'guardrail', 'label' => 'Guarda-corpos', 'score' => 2],
        ['code' => 'floor_plate_or_grating', 'label' => 'Chapas/grades de piso', 'score' => 2],
        ['code' => 'secondary_structure_auxiliary', 'label' => 'Estruturas auxiliares de estruturas secundárias', 'score' => 2],
        ['code' => 'roof_or_side_cladding_tie', 'label' => 'Tirantes de cobertura e tapamentos laterais', 'score' => 2],
        ['code' => 'tank_silo_chimney_base', 'label' => 'Base de tanques, silos e chaminés', 'score' => 2],
        ['code' => 'fixed_ladder', 'label' => 'Escada de marinheiro', 'score' => 2],
        ['code' => 'component_fixing_structure', 'label' => 'Estruturas de fixação de componentes', 'score' => 3],
        ['code' => 'secondary_floor_beam', 'label' => 'Vigas de piso secundárias', 'score' => 3],
        ['code' => 'roof_or_cladding_purlin', 'label' => 'Terças de cobertura e fechamentos', 'score' => 3],
        ['code' => 'side_cladding_structure', 'label' => 'Estruturas de fechamento lateral', 'score' => 3],
        ['code' => 'drive_base_on_platform', 'label' => 'Bases de acionamento instaladas sobre plataformas', 'score' => 3],
        ['code' => 'chute_base', 'label' => 'Bases de chutes', 'score' => 3],
        ['code' => 'primary_structure_auxiliary', 'label' => 'Estruturas auxiliares de estruturas primárias', 'score' => 3],
        ['code' => 'truss_post', 'label' => 'Montantes de treliças', 'score' => 4],
        ['code' => 'bracing', 'label' => 'Contraventamentos', 'score' => 4],
        ['code' => 'equipment_support_column', 'label' => 'Colunas de sustentação de equipamentos', 'score' => 4],
        ['code' => 'equipment_support_beam', 'label' => 'Vigas de sustentação de equipamentos', 'score' => 4],
        ['code' => 'equipment_support_base', 'label' => 'Bases de sustentação de equipamentos', 'score' => 4],
        ['code' => 'main_floor_beam', 'label' => 'Vigas de piso principais', 'score' => 4],
        ['code' => 'tank_or_silo_roof', 'label' => 'Teto de tanques/silos', 'score' => 4],
        ['code' => 'structural_support_frame', 'label' => 'Cavalete estrutural de sustentação', 'score' => 4],
        ['code' => 'building_support_column', 'label' => 'Colunas de sustentação de edifícios', 'score' => 5],
        ['code' => 'main_elevation_beam', 'label' => 'Vigas principais de elevações', 'score' => 5],
        ['code' => 'roof_truss', 'label' => 'Tesouras de coberturas', 'score' => 5],
        ['code' => 'gantry_crane_or_hoist_beam', 'label' => 'Pórticos e vigas de ponte rolante e talhas de ligação', 'score' => 5],
        ['code' => 'cantilever_tie', 'label' => 'Tirantes de estruturas em balanço', 'score' => 5],
        ['code' => 'truss_chord_or_diagonal', 'label' => 'Banzos e diagonais de treliças', 'score' => 5],
        ['code' => 'tank_silo_chimney_shell', 'label' => 'Costado de tanques, silos e chaminés', 'score' => 5],
        ['code' => 'bracket_or_connection_plate', 'label' => 'Mão francesa e talas de ligação', 'score' => 5],
    ];

    /** @var list<array{code:string,label:string}> */
    private const CIVIL_TREND_GROUPS = [
        ['code' => 'cracking', 'label' => 'Fissuração'],
        ['code' => 'segregation_and_disaggregation', 'label' => 'Segregação e Desagregação'],
        ['code' => 'reinforcement_corrosion', 'label' => 'Corrosão em armaduras'],
        ['code' => 'chemical_effects', 'label' => 'Efeitos químicos'],
        ['code' => 'permanent_deformation_and_displacement', 'label' => 'Deformação permanente e deslocamentos'],
        ['code' => 'nonconforming_execution', 'label' => 'Execução em desconformidade com projeto'],
        ['code' => 'infiltration', 'label' => 'Infiltração'],
        ['code' => 'anchors', 'label' => 'Chumbadores'],
        ['code' => 'rail_longitudinal_inclination', 'label' => 'Inclinação longitudinal dos trilhos'],
        ['code' => 'rail_vertical_curvature', 'label' => 'Curvatura vertical dos trilhos'],
        ['code' => 'rail_lateral_curvature', 'label' => 'Curvatura lateral dos trilhos'],
        ['code' => 'rail_level_difference', 'label' => 'Desnível entre trilhos'],
        ['code' => 'rail_wear', 'label' => 'Desgaste dos trilhos'],
        ['code' => 'rail_fixing', 'label' => 'Fixação dos trilhos'],
        ['code' => 'sleepers', 'label' => 'Dormentes'],
        ['code' => 'rail_joint', 'label' => 'Emenda dos trilhos'],
    ];

    /** @var list<array{code:string,label:string,options:list<array{code:string,label:string,score:int}>}> */
    private const REC_TREND_GROUPS = [
        ['code' => 'cut_or_hole', 'label' => 'CORTE OU FURO', 'options' => [
            ['code' => 'unplanned_below_10_percent', 'label' => 'Corte ou furo não previsto abaixo de 10% da área da seção', 'score' => 1],
            ['code' => 'secondary_component_absent', 'label' => 'Ausência de componentes secundários', 'score' => 2],
            ['code' => 'unplanned_10_to_20_percent', 'label' => 'Corte ou furo não previsto entre 10% e 20% da área da seção', 'score' => 3],
            ['code' => 'unplanned_20_to_50_percent', 'label' => 'Corte ou furo não previsto entre 20% e 50% da área da seção', 'score' => 4],
            ['code' => 'unplanned_above_50_or_main_absent', 'label' => 'Corte ou furo não previsto acima de 50% da área da seção ou ausência de componente principal', 'score' => 5],
        ]],
        ['code' => 'deformation', 'label' => 'DEFORMAÇÃO', 'options' => [
            ['code' => 'local_without_load_eccentricity', 'label' => 'Amassamento pontual da seção do perfil sem causar excentricidade de carga', 'score' => 1],
            ['code' => 'global_without_load_eccentricity', 'label' => 'Amassamento global da seção do perfil sem causar excentricidade de carga', 'score' => 2],
            ['code' => 'slight', 'label' => 'Leve: menor que 1/500 do vão ou 1% da dimensão do elemento', 'score' => 3],
            ['code' => 'moderate', 'label' => 'Moderada: entre 1/500 e 1/200 do vão ou entre 1% e 5% da dimensão do elemento', 'score' => 4],
            ['code' => 'severe', 'label' => 'Severa: maior que 1/200 do vão ou maior que 5% da dimensão do elemento', 'score' => 5],
        ]],
        ['code' => 'discontinuity', 'label' => 'DESCONTINUIDADE', 'options' => [
            ['code' => 'visible_crack_or_insufficient_weld', 'label' => 'Trinca visível em inspeção visual ou deposição insuficiente de solda', 'score' => 4],
            ['code' => 'total_rupture_or_longitudinal_crack', 'label' => 'Rompimento total do perfil ou trinca ao longo do perfil/ligação', 'score' => 5],
        ]],
        ['code' => 'bolted_connection', 'label' => 'LIG. PARAFUSADA', 'options' => [
            ['code' => 'missing_or_loose_up_to_10_percent', 'label' => 'Ausência ou falta de aperto de até 10% dos elementos de fixação', 'score' => 1],
            ['code' => 'unplanned_hole_or_weld_substitution', 'label' => 'Abertura ou alargamento de furação não projetada e sem acabamento; substituição por solda', 'score' => 2],
            ['code' => 'missing_or_loose_10_to_30_percent', 'label' => 'Ausência ou falta de aperto de 10% a 30% dos elementos de ligação ou uso de parafusos não especificados em projeto', 'score' => 3],
            ['code' => 'missing_or_loose_30_to_50_percent', 'label' => 'Ausência ou falta de aperto de 30% a 50% dos elementos de fixação', 'score' => 4],
            ['code' => 'function_loss_above_50_percent', 'label' => 'Ausência ou falta de aperto que gera perda de função estrutural acima de 50% dos elementos de ligação', 'score' => 5],
        ]],
        ['code' => 'thickness_loss', 'label' => 'PERDA DE ESPESSURA', 'options' => [
            ['code' => 'local_10_to_20_percent', 'label' => 'Perda de espessura localizada de 10% a 20%', 'score' => 2],
            ['code' => 'general_10_to_20_percent', 'label' => 'Perda de espessura generalizada de 10% a 20%', 'score' => 3],
            ['code' => 'local_above_20_percent', 'label' => 'Perda de espessura localizada acima de 20%', 'score' => 4],
            ['code' => 'general_above_20_percent', 'label' => 'Perda de espessura generalizada acima de 20%', 'score' => 5],
        ]],
    ];

    /** @var list<array{code:string,label:string,score:int}> */
    private const TAC_TREND = [
        ['code' => 'above_7', 'label' => 'Acima de 7', 'score' => 1],
        ['code' => 'grade_6_or_7', 'label' => '6 ou 7', 'score' => 2],
        ['code' => 'grade_4_or_5', 'label' => '4 ou 5', 'score' => 3],
        ['code' => 'grade_2_or_3', 'label' => '2 ou 3', 'score' => 4],
        ['code' => 'grade_1_or_0', 'label' => '1 ou 0', 'score' => 5],
    ];

    /** @return Collection<int, DefectClassificationDefinition> */
    public static function classifications(DefectCategory $category): Collection
    {
        return collect(self::CLASSIFICATIONS[$category->value])
            ->map(fn (array $definition): DefectClassificationDefinition => new DefectClassificationDefinition(
                code: $definition['code'],
                name: $definition['name'],
                description: $definition['description'],
                color: $definition['color'],
                position: $definition['position'],
                severity_rank: $definition['position'],
                lower_limit: $definition['lower_limit'],
                upper_limit: $definition['upper_limit'],
            ));
    }

    /** @return array<string, list<array{score:int,color:string}>> */
    public static function gutOptions(): array
    {
        $options = [];
        foreach (GutCriterion::cases() as $criterion) {
            $options[$criterion->value] = [];
            foreach (self::GUT_COLORS as $score => $color) {
                $options[$criterion->value][] = ['score' => $score, 'color' => $color];
            }
        }

        return $options;
    }

    public static function colorForScore(int $score): string
    {
        return self::GUT_COLORS[$score];
    }

    /** @return array<string, mixed> */
    public static function technicalDefinition(
        DefectCategory $category,
        AssetAbcClass|string|null $abcClass = null,
        AtmosphericCorrosivity|string|null $atmosphericCorrosivity = null,
    ): array {
        $isImpactCategory = in_array($category, [DefectCategory::Civil, DefectCategory::StructuralRecovery], true);

        return [
            'catalog_version' => self::VERSION,
            'category' => ['code' => $category->value, 'name' => $category->label()],
            'safety_impact_options' => $isImpactCategory ? self::withColors(self::SAFETY_IMPACTS) : [],
            'asset_impact_options' => $isImpactCategory ? self::withColors(self::ASSET_IMPACTS) : [],
            'urgency_options' => self::withColors(match ($category) {
                DefectCategory::Civil => self::CIVIL_URGENCY,
                DefectCategory::StructuralRecovery => self::REC_URGENCY,
                DefectCategory::AnticorrosiveTreatment => [],
            }),
            'urgency_allows_manual' => $category === DefectCategory::StructuralRecovery,
            'trend_groups' => self::trendGroups($category),
            'trend_options' => $category === DefectCategory::AnticorrosiveTreatment
                ? self::withColors(self::TAC_TREND)
                : [],
            'sources' => [
                'gravity' => $category === DefectCategory::AnticorrosiveTreatment
                    ? self::abcSource($abcClass)
                    : null,
                'urgency' => $category === DefectCategory::AnticorrosiveTreatment
                    ? self::atmosphericSource($atmosphericCorrosivity)
                    : null,
            ],
        ];
    }

    /** @return array{code:string,label:string,score:int,color:string}|null */
    public static function safetyImpact(?string $code): ?array
    {
        return self::findOption(self::SAFETY_IMPACTS, $code);
    }

    /** @return array{code:string,label:string,score:int,color:string}|null */
    public static function assetImpact(?string $code): ?array
    {
        return self::findOption(self::ASSET_IMPACTS, $code);
    }

    /** @return array{code:string,label:string,score:int,color:string}|null */
    public static function urgency(DefectCategory $category, ?string $code): ?array
    {
        return self::findOption(match ($category) {
            DefectCategory::Civil => self::CIVIL_URGENCY,
            DefectCategory::StructuralRecovery => self::REC_URGENCY,
            DefectCategory::AnticorrosiveTreatment => [],
        }, $code);
    }

    /** @return array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}|null */
    public static function trendGroup(DefectCategory $category, ?string $code): ?array
    {
        $group = collect(self::trendGroups($category))->firstWhere('code', $code);

        return is_array($group) ? $group : null;
    }

    /** @return array{code:string,label:string,score:int,color:string}|null */
    public static function trend(DefectCategory $category, ?string $groupCode, ?string $optionCode): ?array
    {
        if ($category === DefectCategory::AnticorrosiveTreatment) {
            return self::findOption(self::TAC_TREND, $optionCode);
        }

        $group = self::trendGroup($category, $groupCode);

        return $group === null ? null : collect($group['options'])->firstWhere('code', $optionCode);
    }

    /** @return list<array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}> */
    private static function trendGroups(DefectCategory $category): array
    {
        if ($category === DefectCategory::AnticorrosiveTreatment) {
            return [];
        }

        if ($category === DefectCategory::Civil) {
            return array_map(fn (array $group): array => [...$group, 'options' => []], self::CIVIL_TREND_GROUPS);
        }

        return array_map(fn (array $group): array => [
            'code' => $group['code'],
            'label' => $group['label'],
            'options' => self::withColors($group['options']),
        ], self::REC_TREND_GROUPS);
    }

    /** @param list<array{code:string,label:string,score:int}> $options @return list<array{code:string,label:string,score:int,color:string}> */
    private static function withColors(array $options): array
    {
        return array_map(fn (array $option): array => [
            ...$option,
            'color' => self::colorForScore($option['score']),
        ], $options);
    }

    /** @param list<array{code:string,label:string,score:int}> $options @return array{code:string,label:string,score:int,color:string}|null */
    private static function findOption(array $options, ?string $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }

        $option = collect(self::withColors($options))->firstWhere('code', $code);

        return is_array($option) ? $option : null;
    }

    /** @return array<string, mixed> */
    private static function abcSource(AssetAbcClass|string|null $value): array
    {
        $enum = $value instanceof AssetAbcClass ? $value : AssetAbcClass::tryFrom((string) $value);

        return self::source(
            field: 'equipment.abc_code',
            value: $value instanceof AssetAbcClass ? $value->value : ($value === null ? null : (string) $value),
            label: $enum?->label(),
            score: $enum?->gravity(),
            message: 'Informe um Código ABC válido (A, B, C ou D) no cadastro do equipamento.',
            mapping: AssetAbcClass::options(),
        );
    }

    /** @return array<string, mixed> */
    private static function atmosphericSource(AtmosphericCorrosivity|string|null $value): array
    {
        $enum = $value instanceof AtmosphericCorrosivity ? $value : AtmosphericCorrosivity::tryFrom((string) $value);

        return self::source(
            field: 'inspection.atmospheric_classification',
            value: $value instanceof AtmosphericCorrosivity ? $value->value : ($value === null ? null : (string) $value),
            label: $enum?->label(),
            score: $enum?->urgency(),
            message: 'Informe uma classificação atmosférica válida (C2, C3, C4, C5 ou CX) na inspeção.',
            mapping: AtmosphericCorrosivity::options(),
        );
    }

    /** @param list<array{value:string,label:string,score:int}> $mapping @return array<string, mixed> */
    private static function source(string $field, ?string $value, ?string $label, ?int $score, string $message, array $mapping): array
    {
        return [
            'field' => $field,
            'value' => $value,
            'label' => $label,
            'score' => $score,
            'color' => $score === null ? null : self::colorForScore($score),
            'valid' => $score !== null,
            'message' => $score === null ? $message : null,
            'mapping' => array_map(fn (array $option): array => [
                ...$option,
                'color' => self::colorForScore($option['score']),
            ], $mapping),
        ];
    }
}
