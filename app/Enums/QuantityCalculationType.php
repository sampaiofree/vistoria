<?php

declare(strict_types=1);

namespace App\Enums;

enum QuantityCalculationType: string
{
    case CivilVolume = 'civil_volume';
    case TacArea = 'tac_area';
    case StructuralRecoveryWeight = 'rec_weight';
    case RoofCladdingNotApplicable = 'tel_not_applicable';
}
