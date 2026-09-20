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
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Demo\ViewFirstDemoPresenter;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DefectAssessmentQuantityTest extends TestCase
{
    use RefreshDatabase;

    public function test_singular_quantity_endpoint_was_removed(): void
    {
        [$actor, $assessment] = $this->scenario();

        $this->actingAs($actor)
            ->put("/defect-assessments/{$assessment->public_id}/quantity", [
                'quantity' => ['area' => 1],
            ])
            ->assertNotFound();
    }

    public function test_civil_items_are_appended_and_summed_without_intermediate_rounding(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);

        $this->store($actor, $assessment, [
            'description' => 'Trecho 1',
            'quantity' => ['length' => 2, 'height' => 0.5, 'width' => 0.3, 'quantity' => 2],
        ])->assertSessionHasNoErrors();
        $this->store($actor, $assessment, [
            'description' => 'Trecho 2',
            'quantity' => ['length' => 1, 'height' => 0.4, 'width' => 0.2, 'quantity' => 3],
        ])->assertSessionHasNoErrors();

        $items = $assessment->quantities()->get();
        $this->assertCount(2, $items);
        $this->assertSame([1, 2], $items->pluck('position')->all());
        $this->assertSame('0.6000000000000000', $items[0]->measurement_value);
        $this->assertSame('0.2400000000000000', $items[1]->measurement_value);
        $this->assertSame(MeasurementUnit::CubicMeter, $items[0]->measurement_unit);

        $technical = app(ViewFirstDemoPresenter::class)->defectTechnicalData($assessment->defect, $assessment->fresh());
        $this->assertSame('0.8400000000000000', $technical['quantity_summary']['total_raw']);
        $this->assertSame('0,84 m³', $technical['quantity_summary']['total_label']);

        $this->actingAs($actor)->get(route('defect-assessments.show', $assessment))
            ->assertInertia(fn (Assert $page) => $page
                ->has('quantities', 2)
                ->where('quantities.0.description', 'Trecho 1')
                ->where('quantities.1.position', 2)
                ->where('quantity_summary.total_raw', '0.8400000000000000')
                ->has('capabilities.quantity_store_url')
                ->missing('quantity'));
    }

    public function test_tac_accepts_multiple_area_items_and_updates_only_the_selected_item(): void
    {
        [$actor, $assessment] = $this->scenario();
        $this->store($actor, $assessment, ['quantity' => ['area' => '1.1234567890123']]);
        $this->store($actor, $assessment, ['description' => 'Face sul', 'quantity' => ['area' => '2.5']]);
        $second = $assessment->quantities()->where('position', 2)->firstOrFail();

        $this->actingAs($actor)->put(route('defect-assessment-quantities.update', $second), [
            'description' => '  Face   norte  ',
            'quantity' => ['area' => '0.8'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('1.1234567890123000', $assessment->quantities()->where('position', 1)->firstOrFail()->measurement_value);
        $this->assertSame('0.8000000000000000', $second->refresh()->measurement_value);
        $this->assertSame('Face norte', $second->description);
    }

    public function test_rec_supports_different_elements_in_the_same_assessment(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::StructuralRecovery);
        $this->store($actor, $assessment, ['description' => 'Perfil L', 'quantity' => [
            'element' => 'profile_l', 'width' => 76, 'thickness' => 6, 'length' => 2.8, 'quantity' => 2,
        ]]);
        $this->store($actor, $assessment, ['description' => 'Guarda-corpo', 'quantity' => [
            'element' => 'guardrail', 'length' => 3, 'quantity' => 1,
        ]]);
        $this->store($actor, $assessment, ['description' => 'Chapa lisa', 'quantity' => [
            'element' => 'smooth_plate', 'width' => 1000, 'length' => 1000,
            'thickness' => '3.0956738853503185', 'quantity' => 1,
        ]]);

        $items = $assessment->quantities()->get();
        $this->assertSame(['profile_l', 'guardrail', 'smooth_plate'], $items->pluck('rec_element')->map->value->all());
        $this->assertTrue($items->every(fn ($item): bool => $item->measurement_unit === MeasurementUnit::Kilogram));
        $technical = app(ViewFirstDemoPresenter::class)->defectTechnicalData($assessment->defect, $assessment->fresh());
        $this->assertSame('152,81 kg', $technical['quantity_summary']['total_label']);
    }

    public function test_deleting_an_intermediate_item_renumbers_the_remaining_positions(): void
    {
        [$actor, $assessment] = $this->scenario();
        foreach ([1, 2, 3] as $area) {
            $this->store($actor, $assessment, ['quantity' => compact('area')]);
        }
        $middle = $assessment->quantities()->where('position', 2)->firstOrFail();

        $this->actingAs($actor)
            ->delete(route('defect-assessment-quantities.destroy', $middle))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $items = $assessment->quantities()->get();
        $this->assertSame([1, 2], $items->pluck('position')->all());
        $this->assertSame([1.0, 3.0], $items->map->value()->all());
    }

    public function test_database_enforces_unique_position_per_assessment_but_allows_many_items(): void
    {
        [, $assessment] = $this->scenario();
        DefectAssessmentQuantity::factory()->forAssessment($assessment)->create(['position' => 1]);
        DefectAssessmentQuantity::factory()->forAssessment($assessment)->create(['position' => 2]);

        $this->assertSame(2, $assessment->quantities()->count());
        $this->expectException(QueryException::class);
        DefectAssessmentQuantity::factory()->forAssessment($assessment)->create(['position' => 2]);
    }

    public function test_published_assessment_rebuilds_aggregate_snapshot_after_item_changes(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $this->store($actor, $assessment, ['quantity' => ['length' => 2, 'height' => 0.5, 'width' => 0.3, 'quantity' => 2]]);
        $this->locateAssessment($assessment);
        $assessment->update([
            'status' => DefectAssessmentStatus::Complete,
            'assessed_at' => now(),
            'defect_snapshot' => ['version' => 2],
            'quantity_snapshot' => ['legacy' => true],
        ]);

        $this->store($actor, $assessment, ['quantity' => ['length' => 1, 'height' => 0.4, 'width' => 0.2, 'quantity' => 3]])
            ->assertSessionHasNoErrors();

        $snapshot = $assessment->refresh()->quantity_snapshot;
        $this->assertSame(DefectAssessmentStatus::Complete, $assessment->status);
        $this->assertSame(2, $snapshot['snapshot_version']);
        $this->assertSame(2, $snapshot['item_count']);
        $this->assertSame('0.8400000000000000', $snapshot['total']);
        $this->assertCount(2, $snapshot['items']);
        $this->assertSame(1, $snapshot['items'][0]['position']);
    }

    public function test_deleting_the_last_item_reopens_a_published_assessment(): void
    {
        [$actor, $assessment] = $this->scenario();
        $this->store($actor, $assessment, ['quantity' => ['area' => 2]]);
        $this->locateAssessment($assessment);
        $assessment->update([
            'status' => DefectAssessmentStatus::Complete,
            'assessed_at' => now(),
            'defect_snapshot' => ['version' => 2],
            'quantity_snapshot' => ['total' => '2', 'measurement_unit' => 'm2'],
        ]);
        $item = $assessment->quantities()->firstOrFail();

        $this->actingAs($actor)->delete(route('defect-assessment-quantities.destroy', $item))
            ->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(DefectAssessmentStatus::Draft, $assessment->status);
        $this->assertNull($assessment->quantity_snapshot);
        $this->assertNull($assessment->defect_snapshot);
        $this->assertSame(0, $assessment->quantities()->count());
    }

    public function test_server_rejects_calculated_values_units_empty_payload_and_long_description(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $valid = ['length' => 1, 'height' => 1, 'width' => 1, 'quantity' => 1];

        $this->store($actor, $assessment, [
            'description' => str_repeat('a', 181),
            'quantity' => [...$valid, 'measurement_value' => 999, 'measurement_unit' => 'm2'],
        ])->assertSessionHasErrors(['description', 'quantity']);
        $this->store($actor, $assessment, ['quantity' => []])->assertSessionHasErrors('quantity');
        $this->assertSame(0, $assessment->quantities()->count());
    }

    public function test_civil_quantity_rejects_non_decimal_or_non_positive_dimensions(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $valid = ['length' => 1, 'height' => 1, 'width' => 1, 'quantity' => 1];

        foreach ([
            'letters' => [[...$valid, 'width' => 'abc'], 'quantity.width'],
            'scientific notation' => [[...$valid, 'width' => '1e3'], 'quantity.width'],
            'zero' => [[...$valid, 'height' => 0], 'quantity.height'],
            'negative' => [[...$valid, 'length' => -1], 'quantity.length'],
        ] as [$quantity, $field]) {
            $this->store($actor, $assessment, ['quantity' => $quantity])
                ->assertSessionHasErrors($field);
        }

        $this->assertSame(0, $assessment->quantities()->count());
    }

    public function test_civil_quantity_form_displays_units_and_blocks_exponent_keys(): void
    {
        $source = file_get_contents(resource_path('js/pages/DefectAssessments/Show.vue'));

        $this->assertStringContainsString("inputmode=\"decimal\"", $source);
        $this->assertStringContainsString('@keydown="blockInvalidNumberKey"', $source);
        $this->assertStringContainsString("['e', 'E', '+', '-']", $source);
        $this->assertStringContainsString("field.key === 'quantity' ? 'un.' : 'm'", $source);
        $this->assertStringContainsString('civilInputUnit(key)', $source);
    }

    public function test_quantity_item_cannot_be_changed_by_another_organization(): void
    {
        [$actor, $assessment] = $this->scenario();
        $this->store($actor, $assessment, ['quantity' => ['area' => 2]]);
        $item = $assessment->quantities()->firstOrFail();
        $outsider = User::factory()->for(Organization::factory())->create([
            'operational_role' => OperationalRole::Inspector,
        ]);

        $this->actingAs($outsider)->put(route('defect-assessment-quantities.update', $item), [
            'quantity' => ['area' => 99],
        ])->assertForbidden();
        $this->assertSame(2.0, $item->refresh()->value());
    }

    public function test_report_uses_only_the_published_aggregate_total(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $this->store($actor, $assessment, ['quantity' => ['length' => 2, 'height' => 0.5, 'width' => 0.3, 'quantity' => 2]]);
        $this->store($actor, $assessment, ['quantity' => ['length' => 1, 'height' => 0.4, 'width' => 0.2, 'quantity' => 3]]);
        $this->locateAssessment($assessment);
        $technical = app(ViewFirstDemoPresenter::class)->defectTechnicalData($assessment->defect, $assessment->fresh());
        $assessment->update([
            'status' => DefectAssessmentStatus::Complete,
            'quantity_snapshot' => [
                'source' => 'native_quantity_catalog', 'snapshot_version' => 2, 'category' => 'CV',
                'measurement_unit' => 'm3', 'item_count' => 2,
                'total' => $technical['quantity_summary']['total_raw'],
                'items' => $assessment->quantities()->get()->map->snapshot()->all(),
            ],
        ]);

        $report = app(InspectionLocationReportComposer::class)->compose($assessment->inspection);
        $quantity = $report['sheets'][0]['maps'][0]['damage_rows'][0]['quantity'];
        $this->assertSame('0,84', $quantity['value']);
        $this->assertSame('0.8400000000000000', $quantity['raw_value']);
        $this->assertSame('M³', $quantity['unit']);
    }

    private function store(User $actor, DefectAssessment $assessment, array $data): TestResponse
    {
        return $this->actingAs($actor)->post(
            route('defect-assessments.quantities.store', $assessment),
            $data,
        )->assertRedirect();
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
