<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Inspections\CreateInspection;
use App\Enums\DefectAssessmentCondition;
use App\Enums\InspectionStatus;
use App\Enums\PhotoProcessingStatus;
use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Inspections\PreviousInspectionContentCopier;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Explicit, local-only fixture for reviewing report photo comparisons. */
final class LocalReportComparisonSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Esta demonstração só pode ser criada no ambiente local.');
        }

        $source = Inspection::query()->where('number', 'INS-2026-000001')->firstOrFail();
        if ($source->status !== InspectionStatus::Released) {
            throw new RuntimeException('A inspeção de origem precisa estar liberada.');
        }

        $successors = $source->nextInspections()->get();
        if ($successors->isNotEmpty()) {
            if ($successors->count() === 1 && data_get($successors->first()->context_snapshot, 'demo_comparison_fixture') === true) {
                $this->command?->info('Reinspeção de demonstração já existe: '.route('inspections.report-preview', $successors->first()));

                return;
            }

            throw new RuntimeException('Já existe uma reinspeção deste equipamento; a demonstração não será criada.');
        }

        $sources = $source->defectAssessments()->with(['photos', 'location', 'quantities'])
            ->where('status', 'complete')->get()->sortBy('defect_id')->values();
        if ($sources->count() !== 6 || $sources->sum(fn (DefectAssessment $assessment): int => $assessment->photos->count()) !== 12) {
            throw new RuntimeException('A origem não contém as seis avaliações e doze fotos esperadas.');
        }

        $actor = User::query()->whereKey($source->created_by)->firstOrFail();
        app(TenantContext::class)->set($source->equipment->organization);
        $copiedFiles = [];

        try {
            $inspection = DB::transaction(function () use ($source, $sources, $actor, &$copiedFiles): Inspection {
                $day = today();
                $inspection = app(CreateInspection::class)->handle($actor, $source->equipment, [
                    'planned_start_on' => $day,
                    'planned_end_on' => $day,
                    'reinspection_defect_ids' => $sources->pluck('defect_id')->all(),
                    'service_order' => 'DEMO-COMPARACAO',
                ], $copiedFiles);

                if ($inspection->previous_inspection_id !== $source->id) {
                    throw new RuntimeException('A reinspeção não foi vinculada à origem esperada.');
                }

                foreach ($sources as $previous) {
                    $assessment = $previous->replicate(['id', 'public_id', 'created_at', 'updated_at']);
                    $assessment->inspection_id = $inspection->id;
                    $assessment->previous_assessment_id = $previous->id;
                    $assessment->condition = DefectAssessmentCondition::Reinspected;
                    $assessment->comment = 'DEMONSTRAÇÃO FICTÍCIA — reinspeção para conferir o layout de comparação. '.($previous->comment ?? 'Dados técnicos e imagens repetidos intencionalmente.');
                    $assessment->internal_notes = null;
                    $assessment->assessed_at = now();
                    $assessment->created_by = $actor->id;
                    $assessment->updated_by = $actor->id;
                    $assessment->save();

                    if ($previous->location !== null) {
                        $location = $previous->location->replicate(['id', 'public_id', 'created_at', 'updated_at']);
                        $location->inspection_id = $inspection->id;
                        $location->defect_assessment_id = $assessment->id;
                        $location->created_by = $actor->id;
                        $location->updated_by = $actor->id;
                        $location->save();
                    }

                    foreach ($previous->quantities as $priorQuantity) {
                        $quantity = $priorQuantity->replicate(['id', 'public_id', 'created_at', 'updated_at']);
                        $quantity->inspection_id = $inspection->id;
                        $quantity->defect_assessment_id = $assessment->id;
                        $quantity->save();
                    }

                    foreach ($previous->photos as $priorPhoto) {
                        $this->copyPhoto($priorPhoto, $assessment, $inspection, $actor, $copiedFiles);
                    }
                }

                $inspection->update([
                    'status' => InspectionStatus::Released,
                    'inspected_on' => $day,
                    'started_at' => now(),
                    'field_completed_at' => now(),
                    'reviewed_at' => now(),
                    'approved_at' => now(),
                    'released_at' => now(),
                    'report_date' => $day,
                    'report_responsibles_snapshot' => $source->report_responsibles_snapshot,
                    'general_notes' => $source->general_notes,
                    'general_drawing' => $source->general_drawing,
                    'first_page_text_template' => $source->first_page_text_template,
                    'context_snapshot' => [...$inspection->context_snapshot, 'demo_comparison_fixture' => true],
                ]);
                InspectionStatusHistory::query()->create([
                    'organization_id' => $inspection->organization_id,
                    'inspection_id' => $inspection->id,
                    'from_status' => InspectionStatus::Planned,
                    'to_status' => InspectionStatus::Released,
                    'changed_by' => $actor->id,
                    'reason' => 'DEMONSTRAÇÃO FICTÍCIA — liberação local para conferência de layout.',
                    'created_at' => now(),
                ]);

                return $inspection->refresh();
            });
        } catch (Throwable $exception) {
            app(PreviousInspectionContentCopier::class)->deleteFiles($copiedFiles);
            throw $exception;
        } finally {
            app(TenantContext::class)->clear();
        }

        $this->command?->info('Prévia da reinspeção fictícia: '.route('inspections.report-preview', $inspection));
    }

    /** @param array<int, array{disk:string,path:string}> $copiedFiles */
    private function copyPhoto(AssessmentPhoto $source, DefectAssessment $assessment, Inspection $inspection, User $actor, array &$copiedFiles): void
    {
        if ($source->processing_status !== PhotoProcessingStatus::Ready || $source->optimized_path === null || $source->thumbnail_path === null) {
            throw new RuntimeException('Uma foto de origem não está pronta para cópia.');
        }

        $photo = $source->replicate(['id', 'public_id', 'created_at', 'updated_at', 'deleted_at']);
        $photo->inspection_id = $inspection->id;
        $photo->defect_assessment_id = $assessment->id;
        $photo->original_path = null;
        $photo->optimized_path = null;
        $photo->thumbnail_path = null;
        $photo->uploaded_by = $actor->id;
        $photo->uploaded_at = now();
        $photo->processed_at = now();
        $photo->save();

        $directory = sprintf('organizations/%d/inspections/%s/assessments/%s/%s', $inspection->organization_id, $inspection->public_id, $assessment->public_id, $photo->public_id);
        $disk = Storage::disk($source->disk);
        $paths = [];
        foreach (['optimized' => $source->optimized_path, 'thumbnail' => $source->thumbnail_path] as $variant => $sourcePath) {
            $target = $directory.'/'.$variant.'.webp';
            $copiedFiles[] = ['disk' => $source->disk, 'path' => $target];
            if (! $disk->copy($sourcePath, $target)) {
                throw new RuntimeException('Não foi possível copiar a foto de origem.');
            }
            $paths[$variant.'_path'] = $target;
        }
        $photo->update($paths);
    }
}
