<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\MeasurementUnit;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DefectAssessmentQuantityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspector_can_create_and_replace_the_single_quantity(): void
    {
        [$actor, $assessment] = $this->scenario();

        $this->actingAs($actor)
            ->put(route('defect-assessments.quantity.update', $assessment), [
                'quantity' => [
                    'measurement_value' => 2.5,
                    'measurement_unit' => MeasurementUnit::SquareMeter->value,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $quantity = $assessment->quantity()->firstOrFail();

        $this->assertSame(2.5, $quantity->value());
        $this->assertSame(MeasurementUnit::SquareMeter, $quantity->measurement_unit);

        $this->actingAs($actor)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('quantity.measurement_value', 2.5)
                ->where('quantity.measurement_unit', MeasurementUnit::SquareMeter->value)
                ->has('capabilities.quantity_url')
                ->missing('quantities'));

        $this->actingAs($actor)
            ->put(route('defect-assessments.quantity.update', $assessment), [
                'quantity' => [
                    'measurement_value' => 0.8,
                    'measurement_unit' => MeasurementUnit::Meter->value,
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, $assessment->quantity()->count());
        $this->assertSame($quantity->id, $assessment->quantity()->firstOrFail()->id);
        $this->assertSame(0.8, $assessment->quantity()->firstOrFail()->value());
        $this->assertSame(MeasurementUnit::Meter, $assessment->quantity()->firstOrFail()->measurement_unit);
    }

    public function test_inspector_can_remove_the_quantity(): void
    {
        [$actor, $assessment] = $this->scenario();
        DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();

        $this->actingAs($actor)
            ->put(route('defect-assessments.quantity.update', $assessment), [
                'quantity' => null,
            ])
            ->assertRedirect();

        $this->assertNull($assessment->quantity()->first());
    }

    public function test_changing_quantity_reopens_a_complete_assessment(): void
    {
        [$actor, $assessment] = $this->scenario();
        $assessment->update([
            'status' => DefectAssessmentStatus::Complete,
            'assessed_at' => now(),
            'defect_snapshot' => ['defect' => ['code' => $assessment->defect->code]],
        ]);
        DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();

        $this->actingAs($actor)
            ->put(route('defect-assessments.quantity.update', $assessment), [
                'quantity' => [
                    'measurement_value' => 0.35,
                    'measurement_unit' => MeasurementUnit::CubicMeter->value,
                ],
            ])
            ->assertRedirect();

        $assessment->refresh();

        $this->assertSame(DefectAssessmentStatus::Draft, $assessment->status);
        $this->assertNull($assessment->assessed_at);
        $this->assertNull($assessment->defect_snapshot);
        $this->assertSame(MeasurementUnit::CubicMeter, $assessment->quantity()->firstOrFail()->measurement_unit);
    }

    public function test_invalid_quantity_is_rejected_without_replacing_existing_value(): void
    {
        [$actor, $assessment] = $this->scenario();
        $existing = DefectAssessmentQuantity::factory()->forAssessment($assessment)->create([
            'measurement_value' => 1.25,
        ]);

        $this->actingAs($actor)
            ->put(route('defect-assessments.quantity.update', $assessment), [
                'quantity' => [
                    'measurement_value' => 0,
                    'measurement_unit' => 'unsupported',
                ],
            ])
            ->assertSessionHasErrors([
                'quantity.measurement_value',
                'quantity.measurement_unit',
            ]);

        $this->assertSame($existing->id, $assessment->quantity()->firstOrFail()->id);
        $this->assertSame(1.25, $assessment->quantity()->firstOrFail()->value());
    }

    public function test_database_rejects_a_second_quantity_for_the_same_assessment(): void
    {
        [, $assessment] = $this->scenario();
        DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();

        $this->expectException(QueryException::class);

        DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();
    }

    /** @return array{User, DefectAssessment} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress,
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $actor)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create([
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return [$actor, $assessment];
    }
}
