<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class MarkInspectionReportGenerated
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);

        if ($inspection->status !== InspectionStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está aprovada para geração de relatório.',
            ]);
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::Approved],
            InspectionStatus::ReportGenerated,
            [
                'report_generated_at' => now(),
            ],
            'Relatório gerado.',
        );
    }
}
