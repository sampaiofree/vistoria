<?php

declare(strict_types=1);

namespace App\Services\Reports;

final class EquipmentTemplateFields
{
    private const LABELS = [
        'numero_cliente' => 'Número do cliente',
        'numero_interno' => 'Número interno',
        'maintenance_plan_code' => 'Plano de manutenção',
        'maintenance_item_code' => 'Item manutenção',
        'area_code' => 'Area(usina)',
        'area_name' => 'Area.nome',
        'subarea_code' => 'Sub-area',
        'subarea_name' => 'sub-area.nome',
        'task_list_group' => 'GrpLisTar.',
        'task_list_group_counter' => 'Numerador de grupos',
        'tag' => 'Campo de ordenação (TAG)',
        'defect_code_prefix' => 'Prefixo de avaria',
        'name' => 'Denominação do loc.instalação',
        'description' => 'Descrição item de manutenção',
        'abc_code' => 'Código ABC',
        'installation_location' => 'Local de instalação',
    ];

    public function has(string $key): bool
    {
        return array_key_exists($key, self::LABELS);
    }

    public function label(string $key): string
    {
        return self::LABELS[$key];
    }

    /** @return array<int, array{key:string,label:string}> */
    public function options(?string $clientName = null, ?string $organizationName = null): array
    {
        $labels = self::LABELS;
        if ($clientName !== null && $clientName !== '') {
            $labels['numero_cliente'] .= ' ('.$clientName.')';
        }
        if ($organizationName !== null && $organizationName !== '') {
            $labels['numero_interno'] .= ' ('.$organizationName.')';
        }

        $options = [];
        foreach ($labels as $key => $label) {
            $options[] = ['key' => $key, 'label' => $label];
        }

        return $options;
    }
}
