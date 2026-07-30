<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Models\Defect;

final class DefectSnapshotBuilder
{
    public const VERSION = 1;

    public function build(Defect $defect): array
    {
        $defect->loadMissing(['equipment']);

        return [
            'defect' => [
                'public_id' => $defect->public_id,
                'code' => $defect->code,
                'title' => $defect->title,
                'category' => $defect->category->value,
                'category_label' => $defect->category->label(),
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
