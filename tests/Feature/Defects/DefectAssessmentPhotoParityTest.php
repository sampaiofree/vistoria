<?php

declare(strict_types=1);

namespace Tests\Feature\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectCategory;
use App\Enums\PhotoProcessingStatus;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Services\Defects\DefectAssessmentCompletionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DefectAssessmentPhotoParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_requires_at_least_two_ready_photos_in_pairs(): void
    {
        $assessment = $this->assessment();
        $validator = app(DefectAssessmentCompletionValidator::class);

        $this->assertPhotoError($validator, $assessment, 'Adicione pelo menos duas fotografias antes de publicar a avaliação.');

        $this->photo($assessment, 1, ready: true);
        $this->assertPhotoError($validator, $assessment, 'Adicione pelo menos duas fotografias antes de publicar a avaliação.');

        $second = $this->photo($assessment, 2, ready: false);
        $this->assertPhotoError($validator, $assessment, 'Aguarde o processamento de todas as fotografias antes de publicar a avaliação.');

        $second->update(['processing_status' => PhotoProcessingStatus::Ready]);
        $validator->ensureCanComplete($assessment->refresh()->load(['defect', 'photos']));

        $this->photo($assessment, 3, ready: true);
        $this->assertPhotoError($validator, $assessment, 'Adicione mais uma fotografia para publicar a avaliação com um número par de fotografias.');

        $this->photo($assessment, 4, ready: true);
        $validator->ensureCanComplete($assessment->refresh()->load(['defect', 'photos']));
    }

    public function test_canceled_assessment_does_not_require_photo_pairs(): void
    {
        $assessment = $this->assessment();
        $assessment->update([
            'condition' => DefectAssessmentCondition::Canceled,
            'reason' => 'Acesso impedido.',
            'recommendation' => null,
        ]);
        AssessmentPhoto::factory()->for($assessment->inspection)->for($assessment, 'assessment')->failed()->create([
            'organization_id' => $assessment->organization_id,
        ]);

        app(DefectAssessmentCompletionValidator::class)
            ->ensureCanComplete($assessment->refresh()->load(['defect', 'photos']));
        $this->assertSame(1, $assessment->photos()->count());
    }

    private function assessment(): DefectAssessment
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create([
            'category' => DefectCategory::SolidaryStructures,
        ]);

        return DefectAssessment::factory()->forDefect($defect, $inspection)->create([
            'comment' => 'Avaria observada.',
            'recommendation' => 'Acompanhar a avaria.',
        ]);
    }

    private function photo(DefectAssessment $assessment, int $position, bool $ready): AssessmentPhoto
    {
        $factory = AssessmentPhoto::factory()->for($assessment->inspection)->for($assessment, 'assessment');

        return ($ready ? $factory->ready() : $factory)->create([
            'organization_id' => $assessment->organization_id,
            'position' => $position,
        ]);
    }

    private function assertPhotoError(
        DefectAssessmentCompletionValidator $validator,
        DefectAssessment $assessment,
        string $message,
    ): void {
        try {
            $validator->ensureCanComplete($assessment->refresh()->load(['defect', 'photos']));
            $this->fail('A publicação deveria ser bloqueada pelas fotografias.');
        } catch (ValidationException $exception) {
            $this->assertSame([$message], $exception->errors()['photos'] ?? []);
        }
    }
}
