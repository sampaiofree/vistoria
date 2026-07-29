<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionResponsibility: string
{
    case Inspector = 'inspector';
    case Preparer = 'preparer';
    case Reviewer = 'reviewer';
    case Approver = 'approver';
    case Releaser = 'releaser';
}
