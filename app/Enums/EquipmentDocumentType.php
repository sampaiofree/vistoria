<?php

namespace App\Enums;

enum EquipmentDocumentType: string
{
    case GeneralDrawing = 'general_drawing';
    case AssemblyDrawing = 'assembly_drawing';
    case TechnicalDrawing = 'technical_drawing';
    case Manual = 'manual';
    case DataSheet = 'data_sheet';
    case Procedure = 'procedure';
    case PreviousReport = 'previous_report';
    case Memorial = 'memorial';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GeneralDrawing => 'Desenho geral',
            self::AssemblyDrawing => 'Desenho de montagem',
            self::TechnicalDrawing => 'Desenho técnico',
            self::Manual => 'Manual',
            self::DataSheet => 'Ficha técnica',
            self::Procedure => 'Procedimento',
            self::PreviousReport => 'Relatório anterior',
            self::Memorial => 'Memorial',
            self::Other => 'Outro',
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
