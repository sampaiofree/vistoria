<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\DefectCategory;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\AssessmentPhoto;
use App\Models\Client;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionDefectScope;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ReportDefectHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_report_photo_history_stops_at_the_selected_assessment(): void
    {
        [$client, $equipment] = $this->clientScenario();
        $first = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released, 'number' => 'INS-001']);
        $defect = Defect::factory()->forEquipment($equipment, $first)->create(['category' => DefectCategory::SolidaryStructures]);
        $initial = DefectAssessment::factory()->forDefect($defect, $first)->complete()->create([
            'internal_notes' => 'NOTA PRIVADA',
            'classification_code' => 'C3',
        ]);
        $previousPhotos = AssessmentPhoto::factory()->for($first)->for($initial, 'assessment')->ready()->count(2)->create([
            'organization_id' => $equipment->organization_id,
        ]);
        $previousPhotos->each(fn (AssessmentPhoto $photo): bool => $photo->update([
            'optimized_path' => $photo->public_id.'/optimized.webp',
            'thumbnail_path' => $photo->public_id.'/thumbnail.webp',
        ]));
        $second = Inspection::factory()->reinspection($first)->create(['status' => InspectionStatus::Released, 'number' => 'INS-002']);
        $current = DefectAssessment::factory()->forDefect($defect, $second)->complete()->create([
            'previous_assessment_id' => $initial->id,
            'classification_code' => 'C2',
        ]);
        AssessmentPhoto::factory()->for($second)->for($current, 'assessment')->ready()->count(2)->create([
            'organization_id' => $equipment->organization_id,
        ]);
        $future = Inspection::factory()->reinspection($second)->create(['status' => InspectionStatus::Released, 'number' => 'INS-FUTURA']);
        DefectAssessment::factory()->forDefect($defect, $future)->complete()->create(['previous_assessment_id' => $current->id]);

        $url = route('inspections.report-defects.history', [$second, $defect]);
        $this->actingAs($client)->get(route('inspections.report-preview', $second))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.findings.0.assessment.public_id', $current->public_id)
                ->missing('content.findings.0.assessment_url')
                ->where('content.findings.0.report_history_url', $url)
                ->where('content.photographic_documentation.blocks.0.assessment_public_id', $current->public_id)
                ->has('content.photographic_documentation.blocks.0.photos', 2));
        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('assessment_public_id', $current->public_id)
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.inspection_number', 'INS-001')
            ->assertJsonPath('history.0.classification_color', null)
            ->assertJsonCount(2, 'history.0.photos')
            ->assertJsonPath('history.0.photos.0.id', $previousPhotos[0]->public_id)
            ->assertJsonPath('history.0.photos.0.url', route('assessment-photos.show', [$previousPhotos[0], 'optimized']))
            ->assertJsonPath('history.0.photos.1.thumbnail_url', route('assessment-photos.show', [$previousPhotos[1], 'thumbnail']))
            ->assertDontSee('NOTA PRIVADA')
            ->assertDontSee('INS-FUTURA');
        $this->getJson(route('inspections.report-defects.history', [$first, $defect]))
            ->assertOk()
            ->assertJsonPath('assessment_public_id', $initial->public_id)
            ->assertJsonCount(0, 'history');

        $admin = User::factory()->for($equipment->organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $this->actingAs($admin)->getJson($url)
            ->assertOk()
            ->assertJsonPath('assessment_public_id', $current->public_id)
            ->assertJsonCount(1, 'history');
    }

    public function test_selective_reinspection_uses_its_historical_source_and_rejects_unscoped_defects(): void
    {
        [$client, $equipment] = $this->clientScenario();
        $first = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $defect = Defect::factory()->forEquipment($equipment, $first)->create();
        $source = DefectAssessment::factory()->forDefect($defect, $first)->complete()->create([
            'gravity' => 2, 'urgency' => 2, 'trend' => 2, 'gut_score' => 8,
            'gut_snapshot' => ['score' => 8, 'criteria' => ['gravity' => ['score' => 2, 'source' => ['label' => 'Origem mantida']]]],
        ]);
        $unscoped = Defect::factory()->forEquipment($equipment, $first)->create();
        DefectAssessment::factory()->forDefect($unscoped, $first)->complete()->create();
        $second = Inspection::factory()->reinspection($first)->create([
            'status' => InspectionStatus::Released,
            'reinspection_scope_version' => 1,
        ]);
        InspectionDefectScope::create([
            'organization_id' => $equipment->organization_id,
            'inspection_id' => $second->id,
            'defect_id' => $defect->id,
            'source_assessment_id' => $source->id,
            'requires_reinspection' => false,
        ]);

        $this->actingAs($client)->get(route('inspections.report-preview', $second))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.findings.0.assessment.public_id', $source->public_id)
                ->missing('content.findings.0.assessment_url')
                ->where('content.findings.0.technical_details.classification.score', 8)
                ->where('content.findings.0.technical_details.classification.criteria.0.details.0', 'Origem mantida'));
        $this->getJson(route('inspections.report-defects.history', [$second, $defect]))
            ->assertOk()->assertJsonPath('assessment_public_id', $source->public_id)
            ->assertJsonCount(0, 'history');
        $this->getJson(route('inspections.report-defects.history', [$second, $unscoped]))->assertNotFound();
    }

    public function test_previous_assessment_without_photos_is_still_available_for_comparison(): void
    {
        [$client, $equipment] = $this->clientScenario();
        $first = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $defect = Defect::factory()->forEquipment($equipment, $first)->create();
        $previous = DefectAssessment::factory()->forDefect($defect, $first)->complete()->create();
        $second = Inspection::factory()->reinspection($first)->create(['status' => InspectionStatus::Released]);
        DefectAssessment::factory()->forDefect($defect, $second)->complete()->create([
            'previous_assessment_id' => $previous->id,
        ]);

        $this->actingAs($client)->getJson(route('inspections.report-defects.history', [$second, $defect]))
            ->assertOk()
            ->assertJsonCount(1, 'history')
            ->assertJsonCount(0, 'history.0.photos');
    }

    public function test_client_receives_saved_gut_criteria_and_quantities_for_each_assessment(): void
    {
        [$client, $equipment] = $this->clientScenario();
        $first = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $defect = Defect::factory()->forEquipment($equipment, $first)->create(['category' => DefectCategory::Civil]);
        $previous = DefectAssessment::factory()->forDefect($defect, $first)->complete()->create([
            'gravity' => 2, 'urgency' => 3, 'trend' => 4, 'gut_score' => 24,
            'classification_code' => 'CV-3',
            'classification_snapshot' => ['code' => 'CV-3', 'color' => '#123456'],
            'gut_snapshot' => [
                'score' => 24,
                'criteria' => [
                    'gravity' => ['score' => 2, 'color' => '#123456', 'safety_impact' => ['label' => 'Impacto histórico na segurança']],
                    'urgency' => ['score' => 3, 'color' => '#ABCDEF', 'context' => ['label' => 'Contexto histórico']],
                    'trend' => ['score' => 4, 'color' => '#FEDCBA', 'option' => ['label' => 'Tendência histórica']],
                ],
            ],
            'quantity_snapshot' => [
                'items' => [
                    ['position' => 1, 'description' => 'Área histórica', 'category' => 'CV', 'measurement_unit' => 'm2', 'total' => '2'],
                    ['position' => 2, 'description' => 'Volume histórico', 'category' => 'CV', 'measurement_unit' => 'm3', 'total' => '3'],
                ],
                'total' => '0',
            ],
            'internal_notes' => 'NOTA PRIVADA DE CLASSIFICAÇÃO',
        ]);
        $second = Inspection::factory()->reinspection($first)->create(['status' => InspectionStatus::Released]);
        $current = DefectAssessment::factory()->forDefect($defect, $second)->complete()->create([
            'previous_assessment_id' => $previous->id,
            'gravity' => 1, 'urgency' => 2, 'trend' => 3, 'gut_score' => 6,
            'classification_code' => 'CV-5',
            'classification_snapshot' => ['code' => 'CV-5', 'color' => '#FEDCBA'],
            'gut_snapshot' => ['score' => 6, 'criteria' => ['gravity' => ['score' => 1, 'color' => '#102030', 'source' => ['label' => 'Critério atual']]]],
        ]);

        $this->actingAs($client)->get(route('inspections.report-preview', $second))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.findings.0.assessment.public_id', $current->public_id)
                ->where('content.findings.0.technical_details.classification.score', 6)
                ->where('content.findings.0.classification.color', '#FEDCBA')
                ->where('content.findings.0.technical_details.classification.criteria.0.color', '#102030')
                ->where('content.findings.0.technical_details.classification.criteria.1.color', null)
                ->where('content.findings.0.technical_details.classification.criteria.0.details.0', 'Critério atual'));

        $this->getJson(route('inspections.report-defects.history', [$second, $defect]))
            ->assertOk()
            ->assertJsonPath('history.0.technical_details.classification.kind', 'gut')
            ->assertJsonPath('history.0.classification_color', '#123456')
            ->assertJsonPath('history.0.technical_details.classification.score', 24)
            ->assertJsonPath('history.0.technical_details.classification.criteria.0.color', '#123456')
            ->assertJsonPath('history.0.technical_details.classification.criteria.1.color', '#ABCDEF')
            ->assertJsonPath('history.0.technical_details.classification.criteria.2.color', '#FEDCBA')
            ->assertJsonPath('history.0.technical_details.classification.criteria.0.details.0', 'Impacto histórico na segurança')
            ->assertJsonPath('history.0.technical_details.classification.criteria.1.details.0', 'Contexto histórico')
            ->assertJsonPath('history.0.technical_details.classification.criteria.2.details.0', 'Tendência histórica')
            ->assertJsonPath('history.0.technical_details.quantities.0.description', 'Área histórica')
            ->assertJsonPath('history.0.technical_details.quantities.1.description', 'Volume histórico')
            ->assertJsonCount(2, 'history.0.technical_details.quantity_totals')
            ->assertDontSee('NOTA PRIVADA DE CLASSIFICAÇÃO')
            ->assertDontSee('Critério atual');

        $previous->update(['classification_snapshot' => ['code' => 'CV-3', 'color' => 'invalid-color']]);
        $this->getJson(route('inspections.report-defects.history', [$second, $defect]))
            ->assertOk()
            ->assertJsonPath('history.0.classification_code', 'CV-3')
            ->assertJsonPath('history.0.classification_color', null);
    }

    public function test_tel_and_categories_without_scores_keep_their_own_detail_shapes(): void
    {
        [$client, $equipment] = $this->clientScenario();
        $first = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $second = Inspection::factory()->reinspection($first)->create(['status' => InspectionStatus::Released]);
        $telDefect = Defect::factory()->forEquipment($equipment, $first)->create(['category' => DefectCategory::RoofCladding]);
        $telPrevious = DefectAssessment::factory()->forDefect($telDefect, $first)->complete()->create([
            'tel_score' => 12,
            'tel_snapshot' => [
                'height_m' => '12',
                'impact' => ['score' => 4, 'label' => 'Acima de 10 m até 15 m'],
                'fall_risk' => ['score' => 3],
                'damage_group' => ['label' => 'Corrosão'],
                'damage_option' => ['label' => 'Fixação comprometida'],
            ],
        ]);
        DefectAssessment::factory()->forDefect($telDefect, $second)->complete()->create(['previous_assessment_id' => $telPrevious->id]);
        $esDefect = Defect::factory()->forEquipment($equipment, $first)->create(['category' => DefectCategory::SolidaryStructures]);
        $esPrevious = DefectAssessment::factory()->forDefect($esDefect, $first)->complete()->create([
            'gravity' => 2, 'urgency' => 2, 'trend' => 2, 'gut_score' => 8,
        ]);
        DefectAssessment::factory()->forDefect($esDefect, $second)->complete()->create(['previous_assessment_id' => $esPrevious->id]);

        $this->actingAs($client)->getJson(route('inspections.report-defects.history', [$second, $telDefect]))
            ->assertOk()
            ->assertJsonPath('history.0.technical_details.classification.kind', 'tel')
            ->assertJsonPath('history.0.technical_details.classification.score', 12)
            ->assertJsonPath('history.0.technical_details.classification.criteria.0.score', 4)
            ->assertJsonPath('history.0.technical_details.classification.criteria.1.score', 3)
            ->assertJsonPath('history.0.technical_details.classification.height_m', '12')
            ->assertJsonCount(0, 'history.0.technical_details.quantities');
        $this->getJson(route('inspections.report-defects.history', [$second, $esDefect]))
            ->assertOk()
            ->assertJsonPath('history.0.technical_details.classification', null)
            ->assertJsonCount(0, 'history.0.technical_details.quantities');
    }

    public function test_client_history_excludes_technical_data_from_an_unreleased_cycle(): void
    {
        [$client, $equipment] = $this->clientScenario();
        $first = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $defect = Defect::factory()->forEquipment($equipment, $first)->create(['category' => DefectCategory::Civil]);
        $released = DefectAssessment::factory()->forDefect($defect, $first)->complete()->create();
        $middle = Inspection::factory()->reinspection($first)->create(['status' => InspectionStatus::InProgress]);
        $private = DefectAssessment::factory()->forDefect($defect, $middle)->complete()->create([
            'previous_assessment_id' => $released->id,
            'gravity' => 5, 'urgency' => 5, 'trend' => 5, 'gut_score' => 125,
            'gut_snapshot' => ['score' => 125, 'criteria' => ['gravity' => ['score' => 5, 'source' => ['label' => 'CRITÉRIO NÃO LIBERADO']]]],
        ]);
        $last = Inspection::factory()->reinspection($middle)->create(['status' => InspectionStatus::Released]);
        DefectAssessment::factory()->forDefect($defect, $last)->complete()->create(['previous_assessment_id' => $private->id]);

        $this->actingAs($client)->getJson(route('inspections.report-defects.history', [$last, $defect]))
            ->assertOk()
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.public_id', $released->public_id)
            ->assertDontSee('CRITÉRIO NÃO LIBERADO');
    }

    public function test_history_requires_access_to_the_inspection_and_its_defect(): void
    {
        [$client, $equipment] = $this->clientScenario();
        $released = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $open = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        $defect = Defect::factory()->forEquipment($equipment, $released)->create();
        DefectAssessment::factory()->forDefect($defect, $released)->complete()->create();
        DefectAssessment::factory()->forDefect($defect, $open)->draft()->create();
        $otherEquipment = Equipment::factory()->for($equipment->organization)->create();
        $otherDefect = Defect::factory()->forEquipment($otherEquipment)->create();
        $foreign = Inspection::factory()->create(['status' => InspectionStatus::Released]);

        $this->actingAs($client);
        $this->getJson(route('inspections.report-defects.history', [$open, $defect]))->assertForbidden();
        $this->getJson(route('inspections.report-defects.history', [$released, $otherDefect]))->assertNotFound();
        $this->getJson(route('inspections.report-defects.history', [$foreign, $defect]))->assertNotFound();

        $admin = User::factory()->for($equipment->organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $this->actingAs($admin)->getJson(route('inspections.report-defects.history', [$open, $defect]))->assertNotFound();
    }

    /** @return array{User, Equipment} */
    private function clientScenario(): array
    {
        $organization = Organization::factory()->create();
        $client = Client::factory()->for($organization)->create();
        $equipment = Equipment::factory()->inStructure($client)->create();
        $user = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::Client,
            'operational_role' => null,
        ]);

        return [$user, $equipment];
    }
}
