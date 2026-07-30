<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ReleaseInspection
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);
        $this->ensureActorHasResponsibility($inspection, $actor, InspectionResponsibility::Releaser);
        $this->ensureResponsibilityPresent($inspection, InspectionResponsibility::Releaser);

        if ($inspection->status !== InspectionStatus::ReportGenerated) {
            throw ValidationException::withMessages([
                'status' => 'O relatório ainda não foi gerado.',
            ]);
        }

        if ($inspection->report_generated_at === null) {
            throw ValidationException::withMessages([
                'report' => 'O relatório precisa estar gerado para liberar a inspeção.',
            ]);
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::ReportGenerated],
            InspectionStatus::Released,
            [
                'released_at' => now(),
            ],
            'Inspeção liberada.',
        );
    }
}
