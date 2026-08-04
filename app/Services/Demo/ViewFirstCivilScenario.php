<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\DefectAssessmentCondition;

final class ViewFirstCivilScenario
{
    public const DISCIPLINE = 'civil';

    public const DISCIPLINE_LABEL = 'Civil';

    public const CLASSIFICATION_FAMILY = 'CV';

    public const UNIT = 'm³';

    public const PROFILE_VERSION = 4;

    public const REPORT_NUMBER = 'U0306VT-G-6RI002';

    public const REPORT_REVISION = 'R-04';

    public const PROCEDURE_NUMBER = 'T000000-S-2PO006_R-04';

    public const DRAWING = 'U030600-S-551729';

    public const REPORT_DATE = '11/05/2026';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function findings(): array
    {
        $findings = [
            self::finding([
                'sequence' => 1,
                'title' => 'Fissura longitudinal no pedestal de concreto',
                'origin_description' => 'Fissura observada na face norte do pedestal durante a inspeção inicial.',
                'previous_location' => 'Face norte do pedestal, eixo do motor, entre as cotas +0,10 m e +0,55 m.',
                'previous_comment' => 'Fissura longitudinal com abertura média estimada em 0,4 mm e extensão de 0,85 m.',
                'previous_recommendation' => 'Monitorar a abertura e executar selagem após avaliação da causa.',
                'current_condition' => DefectAssessmentCondition::Worsened->value,
                'current_location' => 'Face norte do pedestal, eixo do motor, entre as cotas +0,10 m e +0,70 m.',
                'current_comment' => 'A abertura evoluiu e a fissura alcança aproximadamente 1,20 m de extensão.',
                'current_recommendation' => 'Realizar avaliação estrutural e reparar a fissura em até 30 dias.',
                'gut' => [3, 4, 3],
                'manifestation' => 'Fissura longitudinal',
                'element' => 'Pedestal de concreto',
                'item' => 'CIV-01',
                'impact' => ['code' => 'IMP. SEG.', 'label' => 'Impacto na segurança'],
                'quantities' => [self::quantity('Extensão consolidada', 1.50, 0.80, 1.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral da face norte do pedestal.', 'concrete'),
                    self::photo('Detalhe', 'Detalhe da fissura longitudinal.', 'surface'),
                    self::photo('Contexto', 'Eixo do motor e base de apoio no conjunto.', 'structure'),
                    self::photo('Locação', 'Marcador da ocorrência na planta de referência.', 'repair'),
                ],
            ]),
            self::finding([
                'sequence' => 2,
                'title' => 'Desplacamento do cobrimento na base do motor',
                'origin_description' => 'Perda localizada de cobrimento observada durante a reinspeção.',
                'previous_location' => 'Canto sudoeste da base do motor, junto ao chumbador CH-04.',
                'previous_comment' => 'Desplacamento localizado com área aproximada de 0,12 m².',
                'previous_recommendation' => 'Remover o material solto e recompor o cobrimento na janela de parada.',
                'current_condition' => DefectAssessmentCondition::New->value,
                'current_location' => 'Canto sudoeste da base do motor, junto ao chumbador CH-04.',
                'current_comment' => 'Desplacamento localizado, com área aproximada de 0,18 m² e armadura ainda não exposta.',
                'current_recommendation' => 'Remover o material solto, verificar aderência e recompor o cobrimento.',
                'draft' => true,
                'gut' => [3, 3, 3],
                'manifestation' => 'Desplacamento',
                'element' => 'Base do motor',
                'item' => 'CIV-02',
                'impact' => ['code' => 'IMP. ATIV.', 'label' => 'Impacto na atividade'],
                'quantities' => [self::quantity('Área afetada', 1.20, 1.00, 2.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral da base do motor.', 'concrete'),
                    self::photo('Detalhe', 'Detalhe do desplacamento do cobrimento.', 'surface'),
                    self::photo('Contexto', 'Posição dos chumbadores na base.', 'structure'),
                ],
            ]),
            self::finding([
                'sequence' => 3,
                'title' => 'Corrosão aparente nos chumbadores',
                'origin_description' => 'Oxidação superficial nos chumbadores de fixação do conjunto.',
                'previous_location' => 'Chumbadores CH-01 a CH-04 da base do motor.',
                'previous_comment' => 'Corrosão superficial sem perda de seção mensurável.',
                'previous_recommendation' => 'Limpar e recompor o sistema de proteção anticorrosiva.',
                'current_condition' => DefectAssessmentCondition::Unchanged->value,
                'current_location' => 'Chumbadores CH-01 a CH-04 da base do motor.',
                'current_comment' => 'Condição visual permanece estável, sem evidência de perda adicional de seção.',
                'current_recommendation' => 'Programar limpeza mecânica e proteção anticorrosiva na próxima parada.',
                'gut' => [3, 2, 4],
                'manifestation' => 'Corrosão aparente',
                'element' => 'Chumbadores',
                'item' => 'CIV-03',
                'impact' => ['code' => '-', 'label' => 'Sem impacto direto'],
                'quantities' => [self::quantity('Elementos afetados', 3.10, 1.00, 1.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral dos chumbadores da base.', 'concrete'),
                    self::photo('Detalhe', 'Detalhe da corrosão aparente nos chumbadores.', 'surface'),
                    self::photo('Contexto', 'Posição relativa dos chumbadores na base.', 'structure'),
                    self::photo('Locação', 'Referência de localização da ocorrência.', 'repair'),
                ],
            ]),
            self::finding([
                'sequence' => 4,
                'title' => 'Falha de selagem entre base e piso',
                'origin_description' => 'Descontinuidade no selante da interface entre a base e o piso industrial.',
                'previous_location' => 'Perímetro leste e sul da base do ventilador.',
                'previous_comment' => 'Selante ressecado e descontínuo em aproximadamente 2,4 m.',
                'previous_recommendation' => 'Remover o selante deteriorado e refazer a vedação perimetral.',
                'current_condition' => DefectAssessmentCondition::Unchanged->value,
                'current_location' => 'Perímetro leste e sul da base do ventilador.',
                'current_comment' => 'A falha permanece estável e sem intervenção registrada desde a inspeção anterior.',
                'current_recommendation' => 'Refazer a selagem durante a próxima janela de manutenção.',
                'gut' => [2, 3, 2],
                'manifestation' => 'Falha de selagem',
                'element' => 'Interface base/piso',
                'item' => 'CIV-04',
                'impact' => ['code' => 'IMP. ATIV.', 'label' => 'Impacto na atividade'],
                'quantities' => [self::quantity('Extensão afetada', 1.40, 1.00, 2.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral da interface base/piso.', 'structure'),
                    self::photo('Detalhe', 'Detalhe da falha de selagem.', 'surface'),
                    self::photo('Contexto', 'Perímetro leste e sul da base.', 'concrete'),
                ],
            ]),
            self::finding([
                'sequence' => 5,
                'title' => 'Umidade superficial na canaleta adjacente',
                'origin_description' => 'Manchas de umidade próximas à canaleta de drenagem.',
                'previous_location' => 'Canaleta no lado leste do pedestal, trecho de 1,5 m.',
                'previous_comment' => 'Umidade contínua e presença de depósitos superficiais.',
                'previous_recommendation' => 'Verificar a drenagem e eliminar a origem da umidade.',
                'current_condition' => DefectAssessmentCondition::Improved->value,
                'current_location' => 'Canaleta no lado leste do pedestal, trecho residual de 0,5 m.',
                'current_comment' => 'A área úmida foi reduzida após limpeza da canaleta e não há acúmulo de água.',
                'current_recommendation' => 'Manter inspeção visual trimestral da drenagem.',
                'gut' => [3, 1, 7],
                'manifestation' => 'Umidade superficial',
                'element' => 'Canaleta adjacente',
                'item' => 'CIV-05',
                'impact' => ['code' => '-', 'label' => 'Sem impacto direto'],
                'quantities' => [self::quantity('Área observada', 1.10, 0.50, 5.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral da canaleta adjacente.', 'surface'),
                    self::photo('Detalhe', 'Detalhe das marcas de umidade.', 'surface'),
                    self::photo('Contexto', 'Trecho leste da drenagem próxima ao pedestal.', 'concrete'),
                ],
            ]),
            self::finding([
                'sequence' => 6,
                'title' => 'Fissura capilar no bloco de fundação',
                'origin_description' => 'Fissura capilar sem sinais de movimentação na face oeste.',
                'previous_location' => 'Face oeste do bloco de fundação, cota +0,25 m.',
                'previous_comment' => 'Fissura capilar com 0,35 m de extensão e abertura inferior a 0,2 mm.',
                'previous_recommendation' => 'Selar e manter registro fotográfico para reinspeção.',
                'current_condition' => DefectAssessmentCondition::Repaired->value,
                'current_location' => 'Face oeste do bloco de fundação, cota +0,25 m.',
                'current_comment' => 'Reparo executado, íntegro e sem recorrência visível da fissura.',
                'current_recommendation' => 'Manter acompanhamento nas inspeções periódicas.',
                'gut' => [1, 1, 6],
                'manifestation' => 'Fissura capilar',
                'element' => 'Bloco de fundação',
                'item' => 'CIV-06',
                'impact' => ['code' => '-', 'label' => 'Sem impacto direto'],
                'quantities' => [self::quantity('Trecho reparado', 0.60, 0.30, 1.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral do bloco de fundação.', 'concrete'),
                    self::photo('Detalhe', 'Detalhe do reparo executado na fissura.', 'repair'),
                    self::photo('Locação', 'Referência da face oeste do bloco.', 'structure'),
                ],
            ]),
            self::finding([
                'sequence' => 7,
                'title' => 'Região posterior do pedestal',
                'origin_description' => 'Região posterior com acesso parcialmente restringido por interferência operacional.',
                'previous_location' => 'Face posterior do pedestal, sob a proteção mecânica.',
                'previous_comment' => 'Inspeção visual parcial; não foram observadas anomalias no trecho acessível.',
                'previous_recommendation' => 'Prever acesso integral na próxima parada programada.',
                'current_condition' => DefectAssessmentCondition::Unchanged->value,
                'current_location' => 'Face posterior do pedestal, sob a proteção mecânica.',
                'current_comment' => 'A região permaneceu parcialmente restrita, porém sem indícios adicionais no trecho acessível.',
                'current_recommendation' => 'Avaliar liberação de acesso na próxima janela de manutenção.',
                'gut' => [2, 2, 5],
                'manifestation' => 'Restrição de acesso',
                'element' => 'Região posterior do pedestal',
                'item' => 'CIV-07',
                'impact' => ['code' => 'IMP. SEG.', 'label' => 'Impacto na segurança'],
                'quantities' => [self::quantity('Área acessível', 2.50, 1.00, 1.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral da região posterior do pedestal.', 'structure'),
                    self::photo('Detalhe', 'Detalhe da interferência operacional.', 'surface'),
                ],
            ]),
            self::finding([
                'sequence' => 8,
                'title' => 'Desagregação superficial no graute de apoio',
                'origin_description' => 'Desagregação localizada no graute de apoio da base.',
                'previous_location' => 'Trecho central do graute de apoio, próximo à base metálica.',
                'previous_comment' => 'Pequenas perdas superficiais sem descontinuidade estrutural.',
                'previous_recommendation' => 'Monitorar e recompor a superfície se houver evolução.',
                'current_condition' => DefectAssessmentCondition::Worsened->value,
                'current_location' => 'Trecho central do graute de apoio, próximo à base metálica.',
                'current_comment' => 'A desagregação avançou e expôs maior área de material friável.',
                'current_recommendation' => 'Remover a camada fragilizada e recompor o graute de apoio.',
                'gut' => [3, 3, 3],
                'manifestation' => 'Desagregação',
                'element' => 'Graute de apoio',
                'item' => 'CIV-08',
                'impact' => ['code' => 'IMP. SEG.', 'label' => 'Impacto na segurança'],
                'quantities' => [self::quantity('Área de recomposição', 1.45, 1.00, 2.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral do graute de apoio.', 'concrete'),
                    self::photo('Detalhe', 'Detalhe da desagregação superficial.', 'surface'),
                ],
            ]),
            self::finding([
                'sequence' => 9,
                'title' => 'Trinca vertical no encontro pedestal-parede',
                'origin_description' => 'Trinca vertical próxima ao encontro do pedestal com a parede lateral.',
                'previous_location' => 'Encontro entre o pedestal e a parede lateral da base.',
                'previous_comment' => 'Trinca fina com extensão reduzida e sem destacamento.',
                'previous_recommendation' => 'Acompanhar a evolução do ponto em próxima inspeção.',
                'current_condition' => DefectAssessmentCondition::Unchanged->value,
                'current_location' => 'Encontro entre o pedestal e a parede lateral da base.',
                'current_comment' => 'A trinca manteve a mesma abertura observada na visita anterior.',
                'current_recommendation' => 'Manter acompanhamento visual e registrar eventual evolução.',
                'gut' => [2, 2, 5],
                'manifestation' => 'Trinca vertical',
                'element' => 'Encontro pedestal-parede',
                'item' => 'CIV-09',
                'impact' => ['code' => '-', 'label' => 'Sem impacto direto'],
                'quantities' => [self::quantity('Extensão da trinca', 2.59, 1.00, 1.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral do encontro pedestal-parede.', 'structure'),
                    self::photo('Detalhe', 'Detalhe da trinca vertical.', 'surface'),
                ],
            ]),
            self::finding([
                'sequence' => 10,
                'title' => 'Vazio localizado na base de reforço lateral',
                'origin_description' => 'Vazio localizado sob a base de reforço lateral da estrutura.',
                'previous_location' => 'Face inferior da base de reforço lateral.',
                'previous_comment' => 'Indício pontual de vazio sem destacamento visível.',
                'previous_recommendation' => 'Verificar aderência e executar correção preventiva.',
                'current_condition' => DefectAssessmentCondition::Worsened->value,
                'current_location' => 'Face inferior da base de reforço lateral.',
                'current_comment' => 'O vazio ficou mais evidente e apresentou bordas frágeis na leitura atual.',
                'current_recommendation' => 'Executar recomposição localizada e verificar aderência global.',
                'gut' => [3, 3, 3],
                'manifestation' => 'Vazio de concretagem',
                'element' => 'Base de reforço lateral',
                'item' => 'CIV-10',
                'impact' => ['code' => 'IMP. ATIV.', 'label' => 'Impacto na atividade'],
                'quantities' => [self::quantity('Volume de recomposição', 2.70, 1.00, 1.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral da base de reforço lateral.', 'concrete'),
                    self::photo('Detalhe', 'Detalhe do vazio localizado.', 'surface'),
                ],
            ]),
            self::finding([
                'sequence' => 11,
                'title' => 'Desnível pontual na soleira de contenção',
                'origin_description' => 'Desnível pontual na soleira de contenção junto à base.',
                'previous_location' => 'Soleira de contenção no lado leste.',
                'previous_comment' => 'Desnível pontual sem interferência operacional relevante.',
                'previous_recommendation' => 'Monitorar e corrigir em programação de manutenção.',
                'current_condition' => DefectAssessmentCondition::Unchanged->value,
                'current_location' => 'Soleira de contenção no lado leste.',
                'current_comment' => 'O desnível manteve a mesma condição de leitura da visita anterior.',
                'current_recommendation' => 'Corrigir o ponto na próxima intervenção civil.',
                'gut' => [3, 2, 2],
                'manifestation' => 'Desnível',
                'element' => 'Soleira de contenção',
                'item' => 'CIV-11',
                'impact' => ['code' => '-', 'label' => 'Sem impacto direto'],
                'quantities' => [self::quantity('Trecho a regularizar', 2.00, 1.00, 2.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral da soleira de contenção.', 'concrete'),
                    self::photo('Detalhe', 'Detalhe do desnível pontual.', 'surface'),
                ],
            ]),
            self::finding([
                'sequence' => 12,
                'title' => 'Destacamento de pintura protetiva no suporte metálico',
                'origin_description' => 'Destacamento de pintura protetiva no suporte metálico adjacente.',
                'previous_location' => 'Suporte metálico lateral, próximo ao pedestal.',
                'previous_comment' => 'Pequenas perdas de pintura sem exposição relevante do substrato.',
                'previous_recommendation' => 'Limpar a superfície e recompor a proteção anticorrosiva.',
                'current_condition' => DefectAssessmentCondition::Improved->value,
                'current_location' => 'Suporte metálico lateral, próximo ao pedestal.',
                'current_comment' => 'A área tratada manteve estabilidade, com redução do avanço visual do destacamento.',
                'current_recommendation' => 'Reforçar o retoque na próxima parada programada.',
                'gut' => [2, 3, 4],
                'manifestation' => 'Destacamento de pintura',
                'element' => 'Suporte metálico',
                'item' => 'CIV-12',
                'impact' => ['code' => '-', 'label' => 'Sem impacto direto'],
                'quantities' => [self::quantity('Área de retoque', 2.40, 1.00, 1.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral do suporte metálico.', 'structure'),
                    self::photo('Detalhe', 'Detalhe do destacamento de pintura.', 'surface'),
                ],
            ]),
            self::finding([
                'sequence' => 13,
                'title' => 'Infiltração residual no encontro da canaleta',
                'origin_description' => 'Infiltração residual no encontro entre a canaleta e o piso.',
                'previous_location' => 'Trecho de encontro da canaleta com o piso industrial.',
                'previous_comment' => 'Pequena infiltração residual observada no alinhamento da canaleta.',
                'previous_recommendation' => 'Melhorar a drenagem e revisar o selante periférico.',
                'current_condition' => DefectAssessmentCondition::Unchanged->value,
                'current_location' => 'Trecho de encontro da canaleta com o piso industrial.',
                'current_comment' => 'A infiltração residual permaneceu no mesmo trecho, sem ampliação visível.',
                'current_recommendation' => 'Manter monitoramento e revisar a drenagem no próximo ciclo.',
                'gut' => [3, 2, 3],
                'manifestation' => 'Infiltração residual',
                'element' => 'Encontro da canaleta',
                'item' => 'CIV-13',
                'impact' => ['code' => '-', 'label' => 'Sem impacto direto'],
                'quantities' => [self::quantity('Trecho de intervenção', 1.40, 1.00, 2.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral do encontro da canaleta.', 'concrete'),
                    self::photo('Detalhe', 'Detalhe da infiltração residual.', 'surface'),
                ],
            ]),
            self::finding([
                'sequence' => 14,
                'title' => 'Fissura transversal no bloco de ancoragem',
                'origin_description' => 'Fissura transversal localizada no bloco de ancoragem secundário.',
                'previous_location' => 'Face superior do bloco de ancoragem secundário.',
                'previous_comment' => 'Fissura curta e sem abertura mensurável na visita anterior.',
                'previous_recommendation' => 'Acompanhar a abertura e registrar nova leitura.',
                'current_condition' => DefectAssessmentCondition::Worsened->value,
                'current_location' => 'Face superior do bloco de ancoragem secundário.',
                'current_comment' => 'A fissura tornou-se mais evidente e alcançou 1,80 m de extensão consolidada.',
                'current_recommendation' => 'Executar reparo localizado e reavaliar a origem da solicitação.',
                'gut' => [3, 2, 4],
                'manifestation' => 'Fissura transversal',
                'element' => 'Bloco de ancoragem',
                'item' => 'CIV-14',
                'impact' => ['code' => 'IMP. SEG.', 'label' => 'Impacto na segurança'],
                'quantities' => [self::quantity('Extensão consolidada', 1.80, 1.00, 1.00)],
                'photos' => [
                    self::photo('Vista geral', 'Vista geral do bloco de ancoragem.', 'concrete'),
                    self::photo('Detalhe', 'Detalhe da fissura transversal.', 'surface'),
                ],
            ]),
        ];

        foreach ($findings as $index => $finding) {
            $findings[$index] = self::finalize($finding);
        }

        return $findings;
    }

    public static function reportMeta(): array
    {
        return [
            'number' => self::REPORT_NUMBER,
            'revision' => self::REPORT_REVISION,
            'procedure_number' => self::PROCEDURE_NUMBER,
            'drawing' => self::DRAWING,
            'issued_at' => self::REPORT_DATE,
        ];
    }

    public static function findingBySequence(int $sequence): ?array
    {
        foreach (self::findings() as $finding) {
            if ($finding['sequence'] === $sequence) {
                return $finding;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function photos(): array
    {
        $photos = [];
        $sequence = 1;

        foreach (self::findings() as $finding) {
            foreach ($finding['photos'] as $index => $photo) {
                $photos[] = [
                    'id' => sprintf('evidence-%02d-%02d', $finding['sequence'], $index + 1),
                    'sequence' => $sequence,
                    'finding_sequence' => $finding['sequence'],
                    'finding_code' => $finding['code'],
                    'finding_title' => $finding['title'],
                    'group_label' => sprintf('%s · %s', $finding['code'], $finding['title']),
                    'discipline' => self::DISCIPLINE,
                    'discipline_label' => self::DISCIPLINE_LABEL,
                    'classification_family' => self::CLASSIFICATION_FAMILY,
                    'unit' => self::UNIT,
                    'photo_interval' => self::photoInterval($finding['photos'], $index),
                    'status' => $photo['status'],
                    'status_label' => self::photoStatusLabel($photo['status']),
                    'title' => sprintf('%s · %s', $finding['code'], $photo['role']),
                    'caption' => $photo['caption'],
                    'role' => $photo['role'],
                    'role_label' => $photo['role'],
                    'location' => $finding['current_location'],
                    'type_label' => $photo['role'],
                    'visual_variant' => $photo['visual_variant'],
                    'is_primary' => $index === 0,
                ];
                $sequence++;
            }
        }

        return $photos;
    }

    /**
     * @return array<string, mixed>
     */
    private static function finding(array $finding): array
    {
        return array_replace([
            'draft' => false,
            'project' => 'U03-06VT002',
            'drawing' => self::DRAWING,
            'discipline' => self::DISCIPLINE,
            'discipline_label' => self::DISCIPLINE_LABEL,
            'classification_family' => self::CLASSIFICATION_FAMILY,
            'unit' => self::UNIT,
            'reason' => null,
            'impact' => ['code' => '-', 'label' => 'Sem impacto direto'],
            'photos' => [],
            'quantities' => [],
        ], $finding);
    }

    /**
     * @return array<string, mixed>
     */
    private static function finalize(array $finding): array
    {
        $gut = $finding['gut'];
        $score = (int) array_product($gut);
        $classification = self::classificationForScore($score);
        $quantityTotal = self::quantityTotal($finding['quantities']);

        return array_replace($finding, [
            'code' => sprintf('VT009-CV-%03d', $finding['sequence']),
            'photo_count' => count($finding['photos']),
            'quantity_total' => $quantityTotal,
            'quantity_total_label' => self::formatQuantity($quantityTotal),
            'gut_score' => $score,
            'classification' => $classification,
            'classification_code' => $classification['code'],
            'classification_label' => $classification['label'],
            'classification_tone' => $classification['tone'],
            'classification_score_band' => $classification['score_band'],
        ]);
    }

    /**
     * @return string
     */
    private static function photoInterval(array $photos, int $index): string
    {
        $count = count($photos);

        return $count > 1
            ? sprintf('Fotos %02d a %02d', 1, $count)
            : sprintf('Foto %02d', $index + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function classificationForScore(int $score): array
    {
        if ($score <= 0) {
            return [
                'code' => '—',
                'label' => 'Não classificada',
                'tone' => 'neutral',
                'score_band' => null,
                'profile_version' => self::PROFILE_VERSION,
            ];
        }

        $labels = [
            'CV-1' => ['Crítica', 'critical', '75-125'],
            'CV-2' => ['Alta', 'danger', '36-73'],
            'CV-3' => ['Moderada', 'warning', '16-35'],
            'CV-4' => ['Baixa', 'info', '8-15'],
            'CV-5' => ['Mínima', 'success', '1-7'],
        ];

        $code = match (true) {
            $score >= 75 => 'CV-1',
            $score >= 36 => 'CV-2',
            $score >= 16 => 'CV-3',
            $score >= 8 => 'CV-4',
            default => 'CV-5',
        };

        return [
            'code' => $code,
            'label' => $labels[$code][0],
            'tone' => $labels[$code][1],
            'score_band' => $labels[$code][2],
            'profile_version' => self::PROFILE_VERSION,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function photo(string $role, string $caption, string $variant = 'concrete'): array
    {
        return [
            'role' => $role,
            'caption' => $caption,
            'status' => 'ready',
            'visual_variant' => $variant,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function quantity(string $label, float $length, float $height, float $width, int $quantity = 1): array
    {
        return [
            'label' => $label,
            'length' => $length,
            'height' => $height,
            'width' => $width,
            'quantity' => $quantity,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $quantities
     */
    private static function quantityTotal(array $quantities): float
    {
        $total = 0.0;

        foreach ($quantities as $quantity) {
            $total += ($quantity['length'] ?? 0) * ($quantity['height'] ?? 0) * ($quantity['width'] ?? 0) * ($quantity['quantity'] ?? 1);
        }

        return round($total, 2);
    }

    private static function formatQuantity(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private static function photoStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'failed' => 'Falha no processamento',
            default => 'Disponível',
        };
    }
}
