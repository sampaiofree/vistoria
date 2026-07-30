<?php

namespace App\Enums;

enum InspectionStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case AwaitingReview = 'awaiting_review';
    case InCorrection = 'in_correction';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case ReportGenerated = 'report_generated';
    case Released = 'released';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planejada',
            self::InProgress => 'Em inspeção',
            self::AwaitingReview => 'Aguardando revisão',
            self::InCorrection => 'Em correção',
            self::AwaitingApproval => 'Aguardando aprovação',
            self::Approved => 'Aprovada',
            self::ReportGenerated => 'Relatório gerado',
            self::Released => 'Liberada',
            self::Canceled => 'Cancelada',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Released, self::Canceled], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Released, self::Canceled], true);
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
