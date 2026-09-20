<?php

declare(strict_types=1);

namespace App\Enums;

enum StructuralRecoveryElement: string
{
    case ProfileW = 'profile_w';
    case ProfileL = 'profile_l';
    case ProfileU = 'profile_u';
    case ProfileUe = 'profile_ue';
    case SmoothPlate = 'smooth_plate';
    case FlatBar = 'flat_bar';
    case CheckeredPlate = 'checkered_plate';
    case Guardrail = 'guardrail';
    case CagedLadder = 'caged_ladder';
    case TubularProfile = 'tubular_profile';
    case UnequalAngle = 'unequal_angle';
    case Metalon = 'metalon';
    case TeeProfile = 'tee_profile';
    case BoltedConnection = 'bolted_connection';
    case RoofSheet = 'roof_sheet';
    case FloorGrating = 'floor_grating';

    public function label(): string
    {
        return match ($this) {
            self::ProfileW => 'Perfil W',
            self::ProfileL => 'Perfil L',
            self::ProfileU => 'Perfil U',
            self::ProfileUe => 'Perfil UE',
            self::SmoothPlate => 'Chapa lisa',
            self::FlatBar => 'Barra chata',
            self::CheckeredPlate => 'Chapa xadrez',
            self::Guardrail => 'Guarda-corpo',
            self::CagedLadder => 'Escada marinheiro',
            self::TubularProfile => 'Perfil tubular',
            self::UnequalAngle => 'Perfil L desiguais',
            self::Metalon => 'Metalon',
            self::TeeProfile => 'Perfil T',
            self::BoltedConnection => 'Ligação parafusada',
            self::RoofSheet => 'Telhas',
            self::FloorGrating => 'Grade de piso',
        };
    }

    public function mode(): QuantityCalculationMode
    {
        return match ($this) {
            self::BoltedConnection, self::RoofSheet, self::FloorGrating => QuantityCalculationMode::Manual,
            default => QuantityCalculationMode::Calculated,
        };
    }
}
