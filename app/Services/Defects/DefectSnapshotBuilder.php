<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Models\Defect;

final class DefectSnapshotBuilder
{
    public const VERSION = 1;

    public function build(Defect $defect): array
    {
        $defect->loadMissing(['equipment', 'categoryDefinition']);

        $category = $defect->categoryDefinition;

        return [
            'defect' => [
                'public_id' => $defect->public_id,
                'code' => $defect->code,
                'title' => $defect->title,
                'category' => $defect->categoryCode(),
                'category_label' => $defect->categoryLabel(),
                'category_definition' => $category === null ? null : [
                    'public_id' => $category->public_id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'description' => $category->description,
                ],
                'origin_description' => $defect->origin_description,
                'status' => $defect->status->value,
                'sequence_number' => $defect->sequence_number,
            ],
            'equipment' => [
                'public_id' => $defect->equipment->public_id,
                'tag' => $defect->equipment->tag,
                'name' => $defect->equipment->name,
                'defect_code_prefix' => $defect->equipment->defect_code_prefix,
            ],
            'version' => self::VERSION,
        ];
    }
}
