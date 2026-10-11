<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\DefectAssessmentClassificationMethod;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\RecoveryClassification;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Defects\DefectAssessmentCompletionValidator;
use App\Services\InspectionLocations\DefectLocationColor;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionM3Test extends TestCase
{
    use RefreshDatabase;

    public function test_batch_defaults_to_common_and_only_an_m3_predecessor_allows_m3_reinspection(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $initialEquipment = Equipment::factory()->for($organization)->create();
        $commonEquipment = Equipment::factory()->for($organization)->create();
        $m3Equipment = Equipment::factory()->for($organization)->create();
        $m3ToCommonEquipment = Equipment::factory()->for($organization)->create();
        Inspection::factory()->forEquipment($commonEquipment)->create(['status' => InspectionStatus::Released, 'is_m3' => false]);
        Inspection::factory()->forEquipment($m3Equipment)->create(['status' => InspectionStatus::Released, 'is_m3' => true]);
        Inspection::factory()->forEquipment($m3ToCommonEquipment)->create(['status' => InspectionStatus::Released, 'is_m3' => true]);

        $this->actingAs($planner)->post(route('inspections.store'), ['inspections' => [
            $this->record($initialEquipment, $inspector),
        ]])->assertRedirect();
        $this->assertFalse(Inspection::query()->where('equipment_id', $initialEquipment->id)->latest('id')->firstOrFail()->is_m3);

        $this->actingAs($planner)->from(route('inspections.create'))
            ->post(route('inspections.store'), ['inspections' => [$this->record($commonEquipment, $inspector, true)]])
            ->assertSessionHasErrors('inspections.0.is_m3');

        $this->actingAs($planner)->post(route('inspections.store'), ['inspections' => [
            $this->record($m3Equipment, $inspector, true),
        ]])->assertRedirect();
        $this->assertTrue(Inspection::query()->where('equipment_id', $m3Equipment->id)->latest('id')->firstOrFail()->is_m3);

        $this->actingAs($planner)->post(route('inspections.store'), ['inspections' => [
            $this->record($m3ToCommonEquipment, $inspector),
        ]])->assertRedirect();
        $this->assertFalse(Inspection::query()->where('equipment_id', $m3ToCommonEquipment->id)->latest('id')->firstOrFail()->is_m3);
    }

    public function test_modality_can_change_during_planning_and_is_locked_afterward(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Planned,
            'is_m3' => false,
            'service_order' => '0000000001',
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $planner)->create([
            'responsibility' => InspectionResponsibility::Preparer,
        ]);
        $payload = [
            ...$this->record($equipment, $inspector),
            'is_m3' => true,
        ];

        $this->actingAs($planner)->put(route('inspections.update', $inspection), $payload)
            ->assertSessionHasNoErrors();
        $this->assertTrue($inspection->fresh()->is_m3);

        $inspection->update(['status' => InspectionStatus::InProgress]);
        $this->actingAs($planner)->put(route('inspections.update', $inspection), [
            ...$payload, 'is_m3' => false,
        ])->assertForbidden();
        $this->assertTrue($inspection->fresh()->is_m3);
    }

    public function test_m3_limits_new_defects_and_saves_recovery_independently_of_gut(): void
    {
        [$inspector, $inspection] = $this->fieldInspection();

        $this->actingAs($inspector)->get(route('inspections.defects.create', $inspection))
            ->assertInertia(fn (Assert $page) => $page->where('categories.0.code', 'CV')
                ->where('categories.1.code', 'REC')->has('categories', 2));
        $this->actingAs($inspector)->post(route('inspections.defects.store', $inspection), [
            'category' => 'TAC', 'title' => 'Categoria proibida',
        ])->assertSessionHasErrors('category');

        $this->actingAs($inspector)->post(route('inspections.defects.store', $inspection), [
            'category' => 'CV', 'title' => 'Avaria M3',
        ])->assertRedirect();
        $assessment = DefectAssessment::query()->where('inspection_id', $inspection->id)->firstOrFail();
        $this->assertNull($assessment->recovery_classification_code);

        $this->actingAs($inspector)->put(route('defect-assessments.recovery-classification.update', $assessment), [
            'recovery_classification_code' => RecoveryClassification::Punctual->value,
        ])->assertSessionHasNoErrors();
        $assessment->refresh();
        $this->assertSame('Reparo Pontual', $assessment->recovery_classification_snapshot['label']);
        $this->assertSame(RecoveryClassification::Punctual->color(), app(DefectLocationColor::class)->forAssessment($assessment));
        $this->assertNull($assessment->gut_score);

        $assessment->update(['classification_method' => DefectAssessmentClassificationMethod::EngineeringNote]);
        $this->assertSame(RecoveryClassification::Punctual->color(), app(DefectLocationColor::class)->forAssessment($assessment->refresh()));
    }

    public function test_active_assessments_require_recovery_but_canceled_ones_do_not(): void
    {
        [, $inspection] = $this->fieldInspection();
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create(['category' => DefectCategory::Civil]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create(['comment' => 'Comentário']);
        $this->satisfyAssessmentPublicationRequirements($assessment);
        try {
            app(DefectAssessmentCompletionValidator::class)->ensureCanComplete($assessment->refresh()->load(['defect', 'inspection', 'photos']));
            $this->fail('A publicação deveria exigir a classificação de recuperação.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recovery_classification_code', $exception->errors());
        }

        $assessment->update(['classification_method' => DefectAssessmentClassificationMethod::EngineeringNote]);
        try {
            app(DefectAssessmentCompletionValidator::class)->ensureCanComplete($assessment->refresh()->load(['defect', 'inspection', 'photos']));
            $this->fail('A Nota de Engenharia também deveria exigir a classificação de recuperação.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recovery_classification_code', $exception->errors());
        }

        $assessment->update(['condition' => DefectAssessmentCondition::Canceled, 'reason' => 'Não aplicável']);
        app(DefectAssessmentCompletionValidator::class)->ensureCanComplete($assessment->refresh()->load(['defect', 'inspection', 'photos']));

        $assessment->update(['condition' => DefectAssessmentCondition::Treated, 'reason' => null]);
        app(DefectAssessmentCompletionValidator::class)->ensureCanComplete($assessment->refresh()->load(['defect', 'inspection', 'photos']));
    }

    public function test_m3_map_uses_recovery_color_and_legend_while_gut_table_keeps_its_color(): void
    {
        [, $inspection] = $this->fieldInspection();
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create(['category' => DefectCategory::StructuralRecovery]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create([
            'classification_snapshot' => ['code' => 'IE-2', 'color' => '#FFC000'],
            'recovery_classification_code' => RecoveryClassification::Total->value,
            'recovery_classification_snapshot' => ['version' => 1, ...RecoveryClassification::Total->toArray()],
        ]);
        $this->locateAssessment($assessment);

        $map = app(InspectionLocationReportComposer::class)->compose($inspection)['sheets'][0]['maps'][0];
        $this->assertTrue($map['is_m3']);
        $this->assertSame(RecoveryClassification::Total->color(), $map['markers'][0]['style']['fill']);
        $this->assertSame('#FFC000', $map['damage_rows'][0]['classification']['color']);
        $this->assertSame([
            'label' => 'Recuperação Total',
            'color' => RecoveryClassification::Total->color(),
        ], $map['damage_rows'][0]['recovery_classification']);
        $this->assertSame(['Recuperação Total', 'Reparo Pontual', 'Reparo por Oportunidade'],
            array_column($map['classification_legend'], 'code'));

        foreach (RecoveryClassification::cases() as $option) {
            $assessment->update([
                'recovery_classification_code' => $option->value,
                'recovery_classification_snapshot' => ['version' => 1, ...$option->toArray()],
            ]);
            $optionMap = app(InspectionLocationReportComposer::class)->compose($inspection)['sheets'][0]['maps'][0];
            $this->assertSame(['label' => $option->label(), 'color' => $option->color()],
                $optionMap['damage_rows'][0]['recovery_classification']);
        }

        $assessment->update([
            'recovery_classification_code' => RecoveryClassification::Total->value,
            'recovery_classification_snapshot' => [
                'code' => RecoveryClassification::Total->value,
                'label' => 'Recuperação Total (histórico)',
                'color' => '#123456',
            ],
        ]);
        $historicalMap = app(InspectionLocationReportComposer::class)->compose($inspection)['sheets'][0]['maps'][0];
        $this->assertSame('#123456', $historicalMap['markers'][0]['style']['fill']);
        $this->assertSame(['label' => 'Recuperação Total (histórico)', 'color' => '#123456'],
            $historicalMap['damage_rows'][0]['recovery_classification']);
        $this->assertSame(['code' => 'Recuperação Total (histórico)', 'color' => '#123456'],
            $historicalMap['classification_legend'][0]);

        $assessment->update(['recovery_classification_snapshot' => null]);
        $legacyMap = app(InspectionLocationReportComposer::class)->compose($inspection)['sheets'][0]['maps'][0];
        $this->assertNull($legacyMap['damage_rows'][0]['recovery_classification']);
    }

    public function test_m3_engineering_note_map_exposes_recovery_classification_without_gut(): void
    {
        [, $inspection] = $this->fieldInspection();
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'category' => DefectCategory::Civil,
        ]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create([
            'classification_method' => DefectAssessmentClassificationMethod::EngineeringNote,
            'recovery_classification_code' => RecoveryClassification::Punctual->value,
            'recovery_classification_snapshot' => ['version' => 1, ...RecoveryClassification::Punctual->toArray()],
        ]);
        $this->locateAssessment($assessment);

        $map = app(InspectionLocationReportComposer::class)->compose($inspection)['sheets'][0]['maps'][0];

        $this->assertTrue($map['engineering_note_without_quantity']);
        $this->assertNull($map['damage_rows'][0]['gut']);
        $this->assertSame(['label' => 'Reparo Pontual', 'color' => RecoveryClassification::Punctual->color()],
            $map['damage_rows'][0]['recovery_classification']);
    }

    /** @return array{User,Inspection} */
    private function fieldInspection(): array
    {
        $organization = Organization::factory()->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress, 'is_m3' => true,
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);

        return [$inspector, $inspection];
    }

    private function record(Equipment $equipment, User $inspector, bool $isM3 = false): array
    {
        return [
            'equipment_id' => $equipment->id,
            'service_order' => '0000000001',
            'planned_start_on' => '2026-10-10',
            'planned_end_on' => '2026-10-12',
            'inspector_id' => $inspector->id,
            'is_m3' => $isM3,
        ];
    }
}
