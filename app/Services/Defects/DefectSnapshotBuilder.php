<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Models\Defect;
use App\Services\Classification\NativeDefectCatalog;

final class DefectSnapshotBuilder
{
    public const VERSION = 2;

    public function build(Defect $defect): array
    {
        $defect->loadMissing(['equipment']);

        $category = $defect->category;

        return [
            'defect' => [
                'public_id' => $defect->public_id,
                'code' => $defect->code,
                'title' => $defect->title,
                'category' => $defect->categoryCode(),
                'category_label' => $defect->categoryLabel(),
                'category_definition' => $category->toArray(),
                'catalog_version' => NativeDefectCatalog::VERSION,
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
