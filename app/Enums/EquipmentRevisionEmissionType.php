<?php

declare(strict_types=1);

namespace App\Enums;

enum EquipmentRevisionEmissionType: string
{
    case Preliminary = 'A';
    case ForApproval = 'B';
    case ForKnowledge = 'C';
    case ForQuotation = 'D';
    case ForConstruction = 'E';
    case AsPurchased = 'F';
    case AsBuilt = 'G';
    case Canceled = 'H';
    case Approved = 'L';

    public function label(): string
    {
        return match ($this) {
            self::Preliminary => 'Preliminar',
            self::ForApproval => 'Para aprovação',
            self::ForKnowledge => 'Para conhecimento',
            self::ForQuotation => 'Para cotação',
            self::ForConstruction => 'Para construção',
            self::AsPurchased => 'Conforme comprado',
            self::AsBuilt => 'Conforme construído',
            self::Canceled => 'Cancelado',
            self::Approved => 'Aprovado',
        };
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
