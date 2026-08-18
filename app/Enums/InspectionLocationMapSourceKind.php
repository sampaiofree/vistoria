<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionLocationMapSourceKind: string
{
    case ReferenceDocument = 'reference_document';
    case Upload = 'upload';
}
