<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\MeasurementUnit;
use App\Enums\OperationalRole;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Demo\ViewFirstDemoPresenter;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_civil_dimensions_calculate_volumes_with_fractional_quantity_and_replace_the_same_record(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);

        $this->actingAs($actor)->put(route('defect-assessments.quantity.update', $assessment), [
            'quantity' => ['length' => 2.5, 'height' => 0.2, 'width' => 0.4, 'quantity' => 1.5],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $quantity = $assessment->quantity()->firstOrFail();
        $this->assertSame('0.200000000000', $quantity->unit_volume);
        $this->assertSame('0.3000000000000000', $quantity->measurement_value);
        $this->assertSame('1.5000', $quantity->quantity);
        $this->assertSame(MeasurementUnit::CubicMeter, $quantity->measurement_unit);
        $this->assertSame(0.3, $quantity->value());

        $this->get(route('defect-assessments.show', $assessment))->assertInertia(fn (Assert $page) => $page
            ->where('assessment.defect.category', 'CV')
            ->where('quantity.length', 2.5)
            ->where('quantity.height', 0.2)
            ->where('quantity.width', 0.4)
            ->where('quantity.quantity', 1.5)
            ->where('quantity.unit_volume', 0.2)
            ->where('quantity.measurement_value', 0.3)
            ->where('quantity.measurement_unit', 'm3'));

        $this->put(route('defect-assessments.quantity.update', $assessment), [
            'quantity' => ['length' => 3, 'height' => 0.2, 'width' => 0.4, 'quantity' => 2.5],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $assessment->quantity()->count());
        $this->assertSame($quantity->id, $assessment->quantity()->firstOrFail()->id);
        $this->assertSame(0.6, $quantity->refresh()->value());
    }

    public function test_civil_small_volumes_remain_nonzero_in_storage_and_summaries(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);

        $this->actingAs($actor)->put(route('defect-assessments.quantity.update', $assessment), [
            'quantity' => ['length' => '0.0001', 'height' => '0.0001', 'width' => '0.0001', 'quantity' => '0.0001'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $quantity = $assessment->quantity()->firstOrFail();
        $this->assertSame('0.000000000001', $quantity->unit_volume);
        $this->assertSame('0.0000000000000001', $quantity->measurement_value);
        $this->assertSame(1.0e-16, $quantity->value());
        $technical = app(ViewFirstDemoPresenter::class)->defectTechnicalData($assessment->defect, $assessment->fresh());
        $this->assertSame(1.0e-16, $technical['quantity_summary']['total']);
        $this->assertSame('0,0000000000000001 m³', $technical['quantity_summary']['total_label']);
    }

    #[DataProvider('invalidCivilMeasurements')]
    public function test_invalid_civil_measurements_preserve_the_previous_quantity(array $input, string $error): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $existing = DefectAssessmentQuantity::factory()->forAssessment($assessment)->create([
            'length' => 1, 'height' => 1, 'width' => 1, 'quantity' => 2,
            'unit_volume' => 1, 'measurement_value' => 2, 'measurement_unit' => MeasurementUnit::CubicMeter,
        ]);

        $this->actingAs($actor)->put(route('defect-assessments.quantity.update', $assessment), [
            'quantity' => $input,
        ])->assertSessionHasErrors($error);

        $this->assertSame(1, $assessment->quantity()->count());
        $this->assertSame(2.0, $existing->refresh()->value());
        $this->assertSame('1.0000', $existing->length);
    }

    public static function invalidCivilMeasurements(): array
    {
        $valid = ['length' => 1, 'height' => 1, 'width' => 1, 'quantity' => 1];

        return [
            'missing width' => [['length' => 1, 'height' => 1, 'quantity' => 1], 'quantity.width'],
            'zero length' => [[...$valid, 'length' => 0], 'quantity.length'],
            'negative height' => [[...$valid, 'height' => -1], 'quantity.height'],
            'nonnumeric width' => [[...$valid, 'width' => 'abc'], 'quantity.width'],
            'zero quantity' => [[...$valid, 'quantity' => 0], 'quantity.quantity'],
            'negative quantity' => [[...$valid, 'quantity' => -0.5], 'quantity.quantity'],
            'excess precision' => [[...$valid, 'width' => '0.00001'], 'quantity.width'],
            'input overflow' => [[...$valid, 'length' => '10000000000'], 'quantity.length'],
            'total overflow' => [[...$valid, 'length' => 10000, 'height' => 10000, 'width' => 10000], 'quantity'],
            'forged total' => [[...$valid, 'measurement_value' => 999], 'quantity'],
            'forged unit' => [[...$valid, 'measurement_unit' => 'm2'], 'quantity'],
            'forged unit volume' => [[...$valid, 'unit_volume' => 999], 'quantity'],
            'empty set' => [[], 'quantity'],
        ];
    }

    public function test_civil_quantity_can_be_removed_and_cannot_be_changed_by_another_organization(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $input = ['quantity' => ['length' => 1, 'height' => 2, 'width' => 3, 'quantity' => 0.5]];
        $url = route('defect-assessments.quantity.update', $assessment);
        $this->actingAs($actor)->put($url, $input)->assertSessionHasNoErrors();

        $outsider = User::factory()->for(Organization::factory())->create(['operational_role' => OperationalRole::Inspector]);
        $this->actingAs($outsider)->put($url, $input)->assertForbidden();
        $this->assertSame(3.0, $assessment->quantity()->firstOrFail()->value());

        $this->actingAs($actor)->put($url, ['quantity' => null])->assertSessionHasNoErrors();
        $this->assertNull($assessment->quantity()->first());
    }

    public function test_civil_quantity_with_a_marker_stays_published_and_reports_use_total_only_once(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $assessment->update(['status' => DefectAssessmentStatus::Complete, 'assessed_at' => now(), 'defect_snapshot' => ['version' => 2]]);
        $map = InspectionLocationMap::factory()->forInspection($assessment->inspection)->create();
        InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create();

        $this->actingAs($actor)->put(route('defect-assessments.quantity.update', $assessment), [
            'quantity' => ['length' => 2, 'height' => 3, 'width' => 4, 'quantity' => 2.5],
        ])->assertSessionHasNoErrors();

        $this->assertSame(DefectAssessmentStatus::Complete, $assessment->refresh()->status);
        $this->assertSame(['version' => 2], $assessment->defect_snapshot);
        $technical = app(ViewFirstDemoPresenter::class)->defectTechnicalData($assessment->defect, $assessment->fresh());
        $this->assertSame(60.0, $technical['quantity_summary']['total']);
        $report = app(InspectionLocationReportComposer::class)->compose($assessment->inspection);
        $this->assertSame(60.0, $report['sheets'][0]['maps'][0]['damage_rows'][0]['quantity']['value']);
        $this->assertSame('M³', $report['sheets'][0]['maps'][0]['damage_rows'][0]['quantity']['unit']);
    }

    public function test_structural_recovery_keeps_the_generic_quantity_contract(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::StructuralRecovery);
        $url = route('defect-assessments.quantity.update', $assessment);
        $this->actingAs($actor)->put($url, [
            'quantity' => ['measurement_value' => 2.5, 'measurement_unit' => 'm2'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2.5, $assessment->quantity()->firstOrFail()->value());
        $this->assertNull($assessment->quantity()->firstOrFail()->length);
        $this->put($url, ['quantity' => ['length' => 1, 'height' => 1, 'width' => 1, 'quantity' => 1]])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(2.5, $assessment->quantity()->firstOrFail()->value());
    }

    public function test_tac_keeps_four_decimal_places_for_the_generic_value(): void
    {
        [$actor, $assessment] = $this->scenario();
        $this->actingAs($actor)->put(route('defect-assessments.quantity.update', $assessment), [
            'quantity' => ['measurement_value' => '1.123456', 'measurement_unit' => 'm2'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('1.1235', $assessment->quantity()->firstOrFail()->measurement_value);
        $this->assertSame(1.1235, $assessment->quantity()->firstOrFail()->value());
        $this->get(route('defect-assessments.show', $assessment))->assertInertia(fn (Assert $page) => $page
            ->where('quantity.measurement_value', 1.1235)
            ->missing('quantity.length')
            ->missing('quantity.unit_volume'));
    }

    /** @return array{User, DefectAssessment} */
    private function scenario(DefectCategory $category = DefectCategory::AnticorrosiveTreatment): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress,
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $actor)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => $category]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create([
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return [$actor, $assessment];
    }
}
