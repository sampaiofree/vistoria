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
    public const VERSION = 4;

    public const TEL_CATALOG_VERSION = 1;

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
        'TEL' => [
            ['code' => 'TE-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 12, 'upper_limit' => 15, 'description' => 'Tratar em até 1 ano'],
            ['code' => 'TE-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 9, 'upper_limit' => 11, 'description' => 'Tratar em até 2 anos'],
            ['code' => 'TE-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 6, 'upper_limit' => 8, 'description' => 'Tratar em até 3 anos'],
            ['code' => 'TE-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 3, 'upper_limit' => 5, 'description' => 'Intervenção por oportunidade'],
        ],
    ];

    /** @var list<array{code:string,label:string,options:list<array{code:string,label:string,score:int}>}> */
    private const TEL_DAMAGE_GROUPS = [
        ['code' => 'missing_fixing_set', 'label' => 'Ausência de conjunto de fixação', 'options' => [
            ['code' => 'up_to_10_percent', 'label' => 'Até 10%, inclusive', 'score' => 1],
            ['code' => 'above_10_to_20_percent', 'label' => 'Acima de 10% até 20%, inclusive', 'score' => 2],
            ['code' => 'above_20_percent', 'label' => 'Acima de 20%', 'score' => 3],
        ]],
        ['code' => 'missing_roof_components', 'label' => 'Ausência de cumeeira, goiva, calhas, suportes e outros', 'options' => [
            ['code' => 'up_to_10_percent', 'label' => 'Até 10%, inclusive', 'score' => 1],
            ['code' => 'above_10_to_20_percent', 'label' => 'Acima de 10% até 20%, inclusive', 'score' => 2],
            ['code' => 'above_20_percent', 'label' => 'Acima de 20%', 'score' => 3],
        ]],
        ['code' => 'lifeline_support', 'label' => 'Suporte de linha de vida', 'options' => [
            ['code' => 'corrosion_deformation_or_connection_damage', 'label' => 'Corrosão, deformação, danos de ligação', 'score' => 3],
        ]],
        ['code' => 'roof_corrosion', 'label' => 'Corrosão de telhas', 'options' => [
            ['code' => 'localized_corrosion', 'label' => 'Corrosão pontual', 'score' => 1],
            ['code' => 'generalized_without_fixing_impact', 'label' => 'Corrosão generalizada que não compromete a sua fixação', 'score' => 2],
            ['code' => 'generalized_compromises_fixing', 'label' => 'Corrosão generalizada que comprometa a sua fixação', 'score' => 3],
        ]],
        ['code' => 'deformation_or_cut', 'label' => 'Deformação/Corte', 'options' => [
            ['code' => 'localized_up_to_20_without_fixing_impact', 'label' => 'Deformação pontual ou corte de até 20%, inclusive, que não comprometa sua fixação', 'score' => 1],
            ['code' => 'above_20_without_fixing_impact', 'label' => 'Deformação ou corte acima de 20% da área e que não comprometa sua fixação', 'score' => 2],
            ['code' => 'severe_or_compromises_fixing', 'label' => 'Deformação severa ou corte que comprometa a sua fixação', 'score' => 3],
        ]],
        ['code' => 'standards_compliance', 'label' => 'Adequação do telhado/fechamento lateral às normas vigentes', 'options' => [
            ['code' => 'no', 'label' => 'Não', 'score' => 1],
        ]],
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

    /** @var list<array{code:string,label:string,options:list<array{code:string,label:string,score:int}>}> */
    private const CIVIL_URGENCY_CONTEXTS = [
        ['code' => 'function', 'label' => 'Função', 'options' => [
            ['code' => 'non_structural_masonry_wall', 'label' => 'Paredes de alvenaria, sem finalidade estrutural', 'score' => 1],
            ['code' => 'anchor_without_safety_relevance', 'label' => 'Ligações chumbadas sem relevância à segurança, como fixação de guarda-corpos', 'score' => 1],
            ['code' => 'concrete_guardrail', 'label' => 'Guarda-corpos de concreto', 'score' => 2],
            ['code' => 'roof_support_structure', 'label' => 'Estruturas de sustentação de telhados/coberturas', 'score' => 2],
            ['code' => 'anchor_with_falling_object_risk', 'label' => 'Ligações chumbadas cuja falha possibilite queda de objetos, como fixação de monovias', 'score' => 2],
            ['code' => 'stair_structure', 'label' => 'Estruturas de escadas', 'score' => 3],
            ['code' => 'essential_component_anchor', 'label' => 'Ligações chumbadas de componentes essenciais, como equipamentos ou componentes estruturais importantes', 'score' => 3],
            ['code' => 'grade_beam', 'label' => 'Vigas baldrame', 'score' => 3],
            ['code' => 'pile_cap', 'label' => 'Blocos de coroamento', 'score' => 3],
            ['code' => 'primary_structure_auxiliary', 'label' => 'Estruturas auxiliares de estruturas primárias, como mísulas', 'score' => 3],
            ['code' => 'secondary_elevation_beam', 'label' => 'Vigas secundárias de elevações', 'score' => 4],
            ['code' => 'equipment_support_column', 'label' => 'Colunas de sustentação de equipamentos', 'score' => 4],
            ['code' => 'equipment_support_beam', 'label' => 'Vigas de sustentação de equipamentos', 'score' => 4],
            ['code' => 'equipment_support_base', 'label' => 'Bases de sustentação de equipamentos', 'score' => 4],
            ['code' => 'slab_without_people_access', 'label' => 'Lajes sem acesso de pessoas', 'score' => 4],
            ['code' => 'soil_reinforcement', 'label' => 'Estruturas de reforço do solo, como terra armada', 'score' => 4],
            ['code' => 'building_support_column', 'label' => 'Colunas de sustentação de edifícios', 'score' => 5],
            ['code' => 'main_elevation_beam', 'label' => 'Vigas principais de elevações', 'score' => 5],
            ['code' => 'gantry_or_crane_beam', 'label' => 'Pórticos e vigas de ponte rolante', 'score' => 5],
            ['code' => 'cantilever_tie_or_bracket', 'label' => 'Tirantes e mão-francesa de estruturas em balanço', 'score' => 5],
            ['code' => 'slab_with_people_access', 'label' => 'Lajes com acesso de pessoas', 'score' => 5],
            ['code' => 'foundation', 'label' => 'Fundações em geral', 'score' => 5],
            ['code' => 'soil_containment', 'label' => 'Estruturas de contenção do solo, como muros de arrimo e cortinas atirantadas', 'score' => 5],
            ['code' => 'tank_silo_chimney_shell', 'label' => 'Costado de tanques, silos e chaminés', 'score' => 5],
        ]],
        ['code' => 'machine_running_path', 'label' => 'Caminho de Rolamento de Máquinas', 'options' => [
            ['code' => 'component_fixing_structure', 'label' => 'Estrutura de fixação de componentes', 'score' => 3],
            ['code' => 'running_rail', 'label' => 'Trilhos', 'score' => 3],
            ['code' => 'ballast_and_sleepers', 'label' => 'Lastro e Dormentes', 'score' => 4],
            ['code' => 'rail_joint', 'label' => 'Emendas do trilho', 'score' => 4],
        ]],
    ];

    /** @var list<array{code:string,label:string,score:int}> */
    private const REC_STRUCTURAL_FUNCTION_URGENCY = [
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

    /** @var list<array{code:string,label:string,options:list<array{code:string,label:string,score:int}>}> */
    private const REC_TRANSPORTER_TYPES = [
        ['code' => 'elevated_gallery', 'label' => 'Transportador Elevado — Galeria', 'options' => [
            ['code' => 'cross_beam', 'label' => 'Transversina', 'score' => 2],
            ['code' => 'diagonal_or_post', 'label' => 'Diagonal / Montante', 'score' => 3],
            ['code' => 'main_floor_beam', 'label' => 'Viga de piso principal', 'score' => 3],
            ['code' => 'bracket', 'label' => 'Mão francesa', 'score' => 4],
            ['code' => 'top_chord', 'label' => 'Banzo superior', 'score' => 4],
            ['code' => 'bracing', 'label' => 'Contraventamento', 'score' => 4],
            ['code' => 'support_post', 'label' => 'Montante de apoio', 'score' => 4],
            ['code' => 'column', 'label' => 'Coluna', 'score' => 5],
            ['code' => 'main_beam', 'label' => 'Viga principal', 'score' => 5],
            ['code' => 'articulated_pin_or_sliding_support', 'label' => 'Pino de ligação rotulada ou apoio deslizante', 'score' => 5],
            ['code' => 'bottom_chord', 'label' => 'Banzo inferior', 'score' => 5],
            ['code' => 'connection_plates', 'label' => 'Talas de ligação', 'score' => 5],
        ]],
        ['code' => 'elevated_truss_bridge', 'label' => 'Transportador Elevado — Ponte Treliçada', 'options' => [
            ['code' => 'cross_beam', 'label' => 'Transversina', 'score' => 2],
            ['code' => 'diagonal_or_post', 'label' => 'Diagonal / Montante', 'score' => 3],
            ['code' => 'walkway_profile', 'label' => 'Perfil passadiço', 'score' => 3],
            ['code' => 'bracket', 'label' => 'Mão francesa', 'score' => 4],
            ['code' => 'support_post', 'label' => 'Montante de apoio', 'score' => 4],
            ['code' => 'bracing', 'label' => 'Contraventamento', 'score' => 4],
            ['code' => 'secondary_floor_beam', 'label' => 'Viga de piso secundário', 'score' => 4],
            ['code' => 'column', 'label' => 'Coluna', 'score' => 5],
            ['code' => 'main_floor_beam', 'label' => 'Viga de piso principal', 'score' => 5],
            ['code' => 'chords', 'label' => 'Banzos', 'score' => 5],
            ['code' => 'articulated_pin_or_sliding_support', 'label' => 'Pino de ligação rotulada ou apoio deslizante', 'score' => 5],
            ['code' => 'connection_plates', 'label' => 'Talas de ligação', 'score' => 5],
        ]],
        ['code' => 'floor_level', 'label' => 'Transportador a Nível do Piso', 'options' => [
            ['code' => 'beam', 'label' => 'Viga', 'score' => 2],
            ['code' => 'crossmember_or_stringer', 'label' => 'Travessa / Longarina', 'score' => 3],
            ['code' => 'post_or_columnette', 'label' => 'Montante / Coluneta', 'score' => 4],
        ]],
    ];

    /** @var list<array{code:string,label:string,options:list<array{code:string,label:string,score:int}>}> */
    private const CIVIL_TREND_GROUPS = [
        ['code' => 'cracking', 'label' => 'Fissuração', 'options' => [
            ['code' => 'superficial_without_limit_state', 'label' => 'Fissuração superficial, cuja orientação não remeta a estados limites', 'score' => 1],
            ['code' => 'reinforced_compatible_with_caa', 'label' => 'Fissuras em elementos de concreto armado com abertura compatível com a CAA do ativo', 'score' => 2],
            ['code' => 'reinforced_between_caa_and_2mm', 'label' => 'Trincas em elementos de concreto armado com abertura entre a admitida para a CAA e 2,0 mm', 'score' => 3],
            ['code' => 'reinforced_above_2mm', 'label' => 'Rachaduras em elementos de concreto armado com abertura superior a 2,0 mm', 'score' => 4],
            ['code' => 'prestressed_or_structural_mechanism', 'label' => 'Rachaduras não superficiais em elementos protendidos e/ou relacionadas a mecanismos estruturais', 'score' => 5],
        ]],
        ['code' => 'segregation_and_disaggregation', 'label' => 'Segregação e Desagregação', 'options' => [
            ['code' => 'surface_protection_loss', 'label' => 'Perda da camada de proteção superficial do concreto, tal como pintura', 'score' => 1],
            ['code' => 'surface_abrasion_without_exposed_rebar', 'label' => 'Abrasão superficial do concreto, com perda de parte do cobrimento nominal sem armadura aparente', 'score' => 2],
            ['code' => 'small_area_concrete_loss', 'label' => 'Perda de concreto em pequenas áreas, entre 0,1 m² e 0,5 m², com ou sem armadura aparente', 'score' => 3],
            ['code' => 'large_area_concrete_loss', 'label' => 'Perda de concreto em área superior a 0,5 m², com ou sem armadura aparente', 'score' => 4],
            ['code' => 'concrete_rupture_exposed_rebar', 'label' => 'Rompimento do concreto, com armadura aparente', 'score' => 5],
        ]],
        ['code' => 'reinforcement_corrosion', 'label' => 'Corrosão em armaduras', 'options' => [
            ['code' => 'insufficient_cover_without_exposed_rebar', 'label' => 'Cobrimento insuficiente com ou sem desplacamento de concreto. Armadura não exposta', 'score' => 1],
            ['code' => 'exposed_rebar_without_corrosion', 'label' => 'Armadura exposta, sem indício de corrosão ou perda de espessura', 'score' => 2],
            ['code' => 'primary_below_15_or_secondary_above_15', 'label' => 'Armadura principal exposta e corroída, com perda de seção inferior a 15% da seção transversal original; ou armadura secundária exposta e corroída com perda de seção maior que 15%', 'score' => 3],
            ['code' => 'primary_15_to_50_or_deformation', 'label' => 'Armadura principal exposta e corroída, com perda de seção entre 15% e 50% da seção transversal original; elementos estruturais com deformação acima do permitido em norma', 'score' => 4],
            ['code' => 'primary_above_50_or_prestressed_exposed', 'label' => 'Perda de mais de 50% da seção transversal do vergalhão em pelo menos uma das barras da armadura principal; armadura protendida exposta; chumbador exposto, com ou sem corrosão', 'score' => 5],
        ]],
        ['code' => 'chemical_effects', 'label' => 'Efeitos químicos', 'options' => [
            ['code' => 'non_generalized_leaching_or_carbonation', 'label' => 'Lixiviação e carbonatação no concreto não generalizada', 'score' => 1],
            ['code' => 'non_generalized_leaching_and_efflorescence', 'label' => 'Lixiviação no concreto e eflorescência com formação de estalactites de maneira não generalizada', 'score' => 2],
            ['code' => 'generalized_leaching_and_efflorescence', 'label' => 'Lixiviação no concreto e eflorescência, com ou sem formação de estalactites, de maneira generalizada', 'score' => 3],
        ]],
        ['code' => 'permanent_deformation_and_displacement', 'label' => 'Deformação permanente e deslocamentos', 'options' => [
            ['code' => 'slight_nonrecurring', 'label' => 'Deformação leve, causada por evento não recorrente', 'score' => 1],
            ['code' => 'medium_nonrecurring', 'label' => 'Deformação média, causada por evento não recorrente', 'score' => 2],
            ['code' => 'slight_maintenance_or_vehicle_area', 'label' => 'Deformação leve em região de manutenção e/ou passagem de veículos e equipamentos', 'score' => 3],
            ['code' => 'severe_maintenance_or_vehicle_area', 'label' => 'Deformação severa em região de manutenção e/ou passagem de veículos', 'score' => 4],
            ['code' => 'global_load_eccentricity', 'label' => 'Deformação com influência global no elemento, causada por excentricidade de carga', 'score' => 5],
        ]],
        ['code' => 'nonconforming_execution', 'label' => 'Execução em desconformidade com projeto', 'options' => [
            ['code' => 'non_structural_nonconformity', 'label' => 'Inconformidade de elementos não estruturais', 'score' => 1],
            ['code' => 'secondary_up_to_10_percent', 'label' => 'Redução das dimensões em até 10% para estruturas auxiliares ou secundárias; diâmetro da armação secundária diminuído para o primeiro diâmetro comercial abaixo do especificado em projeto; espaçamento das armações aumentado em até 10% em lajes, cisalhamento e pele', 'score' => 2],
            ['code' => 'secondary_up_to_20_or_primary_up_to_10', 'label' => 'Redução das dimensões em até 20% para estruturas auxiliares ou secundárias; redução das dimensões em até 10% para elementos principais; alterações significativas das premissas e dimensionamento que não prejudicam a segurança estrutural', 'score' => 3],
            ['code' => 'additional_loads_or_dimension_reduction', 'label' => 'Inclusão de solicitações adicionais em até 20% das cargas previstas em projeto; redução das dimensões em até 15% para elementos principais e superior a 20% para demais elementos; diâmetro das armaduras principais diminuído para o primeiro diâmetro comercial abaixo do especificado em projeto', 'score' => 4],
            ['code' => 'severe_design_nonconformity', 'label' => 'Inclusão de solicitações que ultrapassem 20% das cargas previstas ou cargas significativas de difícil mensuração; alterações significativas das premissas de projeto; corpos de prova não atendem à resistência de projeto; dimensões de estruturas principais e armação severamente abaixo do indicado em projeto', 'score' => 5],
        ]],
        ['code' => 'infiltration', 'label' => 'Infiltração', 'options' => [
            ['code' => 'localized_without_consequences', 'label' => 'Infiltração insignificante e localizada. Ausência de consequências estruturais ou superficiais para o concreto', 'score' => 1],
            ['code' => 'recurring_non_generalized_abrasion', 'label' => 'Infiltração recorrente. Presença não generalizada de concreto deteriorado por abrasão', 'score' => 2],
            ['code' => 'recurring_flow_without_pressure', 'label' => 'Infiltração recorrente com fluxo sem pressão. Acúmulo de água dificulta a operação do local. Pontos de abrasão generalizada, sem exposição de armadura. Presença mínima ou inexistente de trincas', 'score' => 3],
            ['code' => 'generalized_local_pressure_cracks', 'label' => 'Infiltração generalizada. Presença de trincas pontuais por onde extravasa água com pressão. Acúmulo de água paralisa as operações do local recorrentemente', 'score' => 4],
            ['code' => 'generalized_safety_or_asset_compromise', 'label' => 'Infiltração generalizada. Presença generalizada de trincas e rachaduras, com extravasamento de água com pressão. Desplacamento de concreto com armadura exposta devido à pressão da água. Acúmulo de água compromete a segurança das pessoas e a integridade dos ativos', 'score' => 5],
        ]],
        ['code' => 'anchors', 'label' => 'Chumbadores', 'options' => [
            ['code' => 'generalized_corrosion_without_function_loss', 'label' => 'Corrosão generalizada do chumbador sem perda de função estrutural', 'score' => 1],
            ['code' => 'thread_and_nut_loss_up_to_30', 'label' => 'Perda de espessura de até 30% na rosca e porca', 'score' => 2],
            ['code' => 'thread_and_nut_loss_above_30', 'label' => 'Perda de espessura superior a 30% na rosca e porca', 'score' => 3],
            ['code' => 'anchor_body_crack', 'label' => 'Fissura ou trincas no corpo do chumbador', 'score' => 4],
            ['code' => 'anchor_absent_or_total_thread_nut_loss', 'label' => 'Ausência de chumbador ou perda total de rosca e porca', 'score' => 5],
        ]],
        ['code' => 'rail_longitudinal_inclination', 'label' => 'Inclinação longitudinal dos trilhos', 'options' => [
            ['code' => 'up_to_3mm', 'label' => 'Até 3 mm acima do admissível', 'score' => 1],
            ['code' => 'up_to_6mm', 'label' => 'Até 6 mm acima do admissível', 'score' => 2],
            ['code' => 'up_to_12mm', 'label' => 'Até 12 mm acima do admissível', 'score' => 3],
            ['code' => 'up_to_18mm', 'label' => 'Até 18 mm acima do admissível', 'score' => 4],
            ['code' => 'above_18mm', 'label' => 'Mais de 18 mm acima do admissível', 'score' => 5],
        ]],
        ['code' => 'rail_vertical_curvature', 'label' => 'Curvatura vertical dos trilhos', 'options' => [
            ['code' => 'up_to_2mm', 'label' => 'Até 2 mm acima do admissível', 'score' => 1],
            ['code' => 'up_to_3mm', 'label' => 'Até 3 mm acima do admissível', 'score' => 2],
            ['code' => 'up_to_5mm', 'label' => 'Até 5 mm acima do admissível', 'score' => 3],
            ['code' => 'up_to_8mm', 'label' => 'Até 8 mm acima do admissível', 'score' => 4],
            ['code' => 'above_8mm', 'label' => 'Mais de 8 mm acima do admissível', 'score' => 5],
        ]],
        ['code' => 'rail_lateral_curvature', 'label' => 'Curvatura lateral dos trilhos', 'options' => [
            ['code' => 'up_to_2mm', 'label' => 'Até 2 mm acima do admissível', 'score' => 1],
            ['code' => 'up_to_3mm', 'label' => 'Até 3 mm acima do admissível', 'score' => 2],
            ['code' => 'up_to_5mm', 'label' => 'Até 5 mm acima do admissível', 'score' => 3],
            ['code' => 'up_to_8mm', 'label' => 'Até 8 mm acima do admissível', 'score' => 4],
            ['code' => 'above_8mm', 'label' => 'Mais de 8 mm acima do admissível', 'score' => 5],
        ]],
        ['code' => 'rail_level_difference', 'label' => 'Desnível entre trilhos', 'options' => [
            ['code' => 'up_to_3mm', 'label' => 'Até 3 mm acima do admissível', 'score' => 1],
            ['code' => 'up_to_5mm', 'label' => 'Até 5 mm acima do admissível', 'score' => 2],
            ['code' => 'up_to_10mm', 'label' => 'Até 10 mm acima do admissível', 'score' => 3],
            ['code' => 'up_to_15mm', 'label' => 'Até 15 mm acima do admissível', 'score' => 4],
            ['code' => 'above_15mm', 'label' => 'Mais de 15 mm acima do admissível', 'score' => 5],
        ]],
        ['code' => 'rail_wear', 'label' => 'Desgaste dos trilhos', 'options' => [
            ['code' => 'vertical_wear', 'label' => 'Desgaste vertical', 'score' => 1],
            ['code' => 'lateral_wear', 'label' => 'Desgaste lateral', 'score' => 2],
            ['code' => 'corrugation', 'label' => 'Corrugação', 'score' => 3],
        ]],
        ['code' => 'rail_fixing', 'label' => 'Fixação dos trilhos', 'options' => [
            ['code' => 'loose_fasteners', 'label' => 'Falta de aperto em fixadores', 'score' => 1],
            ['code' => 'corrosion_thickness_loss_above_20', 'label' => 'Corrosão com perda de espessura superior a 20%', 'score' => 2],
            ['code' => 'missing_fasteners', 'label' => 'Ausência de fixadores', 'score' => 3],
        ]],
        ['code' => 'sleepers', 'label' => 'Dormentes', 'options' => [
            ['code' => 'cracks', 'label' => 'Rachaduras', 'score' => 1],
            ['code' => 'uneven_settlement', 'label' => 'Assentamento desnivelado', 'score' => 2],
            ['code' => 'differential_settlement', 'label' => 'Recalque diferencial', 'score' => 3],
        ]],
        ['code' => 'rail_joint', 'label' => 'Emenda dos trilhos', 'options' => [
            ['code' => 'joint_gap', 'label' => 'Folga na emenda dos trilhos', 'score' => 1],
            ['code' => 'joint_alignment', 'label' => 'Alinhamento na emenda dos trilhos', 'score' => 2],
        ]],
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

    /** @return array{catalog_version:int,category:array{code:string,name:string},damage_groups:list<array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}>} */
    public static function telTechnicalDefinition(): array
    {
        return [
            'catalog_version' => self::TEL_CATALOG_VERSION,
            'category' => ['code' => DefectCategory::RoofCladding->value, 'name' => DefectCategory::RoofCladding->label()],
            'damage_groups' => array_map(fn (array $group): array => [
                'code' => $group['code'],
                'label' => $group['label'],
                'options' => self::withColors($group['options']),
            ], self::TEL_DAMAGE_GROUPS),
        ];
    }

    /** @return array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}|null */
    public static function telDamageGroup(?string $code): ?array
    {
        $group = collect(self::telTechnicalDefinition()['damage_groups'])->firstWhere('code', $code);

        return is_array($group) ? $group : null;
    }

    /** @return array{code:string,label:string,score:int,color:string}|null */
    public static function telDamageOption(?string $groupCode, ?string $optionCode): ?array
    {
        $group = self::telDamageGroup($groupCode);

        return $group === null ? null : collect($group['options'])->firstWhere('code', $optionCode);
    }

    /** @return list<array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>,transporter_types:list<array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}>}> */
    public static function recUrgencyMatrices(): array
    {
        return [
            [
                'code' => 'structural_function',
                'label' => 'Função Estrutural',
                'options' => self::withColors(self::REC_STRUCTURAL_FUNCTION_URGENCY),
                'transporter_types' => [],
            ],
            [
                'code' => 'patio_port_transporter',
                'label' => 'Transportadores do Pátio e Porto',
                'options' => [],
                'transporter_types' => array_map(fn (array $type): array => [
                    'code' => $type['code'],
                    'label' => $type['label'],
                    'options' => self::withColors($type['options']),
                ], self::REC_TRANSPORTER_TYPES),
            ],
        ];
    }

    /** @return array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>,transporter_types:list<array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}>}|null */
    public static function recUrgencyMatrix(?string $code): ?array
    {
        $matrix = collect(self::recUrgencyMatrices())->firstWhere('code', $code);

        return is_array($matrix) ? $matrix : null;
    }

    /** @return array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}|null */
    public static function recTransporterType(?string $matrixCode, ?string $typeCode): ?array
    {
        if ($matrixCode !== 'patio_port_transporter') {
            return null;
        }

        $type = collect(self::recUrgencyMatrix($matrixCode)['transporter_types'] ?? [])
            ->firstWhere('code', $typeCode);

        return is_array($type) ? $type : null;
    }

    /** @return array{code:string,label:string,score:int,color:string}|null */
    public static function recUrgencyOption(?string $matrixCode, ?string $transporterTypeCode, ?string $optionCode): ?array
    {
        if ($matrixCode === 'structural_function') {
            return self::findOption(self::REC_STRUCTURAL_FUNCTION_URGENCY, $optionCode);
        }

        $type = self::recTransporterType($matrixCode, $transporterTypeCode);

        return $type === null ? null : collect($type['options'])->firstWhere('code', $optionCode);
    }

    /** @return list<array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}> */
    public static function civilUrgencyContexts(): array
    {
        return array_map(fn (array $context): array => [
            'code' => $context['code'],
            'label' => $context['label'],
            'options' => self::withColors($context['options']),
        ], self::CIVIL_URGENCY_CONTEXTS);
    }

    /** @return array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}|null */
    public static function civilUrgencyContext(?string $code): ?array
    {
        $context = collect(self::civilUrgencyContexts())->firstWhere('code', $code);

        return is_array($context) ? $context : null;
    }

    /** @return array{code:string,label:string,score:int,color:string}|null */
    public static function civilUrgencyOption(?string $contextCode, ?string $optionCode): ?array
    {
        $context = self::civilUrgencyContext($contextCode);

        return $context === null ? null : collect($context['options'])->firstWhere('code', $optionCode);
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
            'urgency_options' => [],
            'urgency_contexts' => $category === DefectCategory::Civil ? self::civilUrgencyContexts() : [],
            'urgency_matrices' => $category === DefectCategory::StructuralRecovery ? self::recUrgencyMatrices() : [],
            'urgency_allows_manual' => false,
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
            DefectCategory::Civil => [],
            DefectCategory::AnticorrosiveTreatment, DefectCategory::StructuralRecovery, DefectCategory::RoofCladding => [],
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
        if ($category === DefectCategory::RoofCladding) {
            return null;
        }

        if ($category === DefectCategory::AnticorrosiveTreatment) {
            return self::findOption(self::TAC_TREND, $optionCode);
        }

        $group = self::trendGroup($category, $groupCode);

        return $group === null ? null : collect($group['options'])->firstWhere('code', $optionCode);
    }

    /** @return list<array{code:string,label:string,options:list<array{code:string,label:string,score:int,color:string}>}> */
    private static function trendGroups(DefectCategory $category): array
    {
        if (in_array($category, [DefectCategory::AnticorrosiveTreatment, DefectCategory::RoofCladding], true)) {
            return [];
        }

        if ($category === DefectCategory::Civil) {
            return array_map(fn (array $group): array => [
                'code' => $group['code'],
                'label' => $group['label'],
                'options' => self::withColors($group['options']),
            ], self::CIVIL_TREND_GROUPS);
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
