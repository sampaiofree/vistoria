<?php

declare(strict_types=1);

namespace Tests\Feature\Defects;

use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AssessmentPhotoRolesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_recreates_legacy_columns_from_manual_order_and_up_removes_them_again(): void
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create();

        $third = AssessmentPhoto::factory()->for($inspection)->for($assessment, 'assessment')->create([
            'organization_id' => $organization->id,
            'position' => 3,
        ]);
        $first = AssessmentPhoto::factory()->for($inspection)->for($assessment, 'assessment')->create([
            'organization_id' => $organization->id,
            'position' => 1,
        ]);
        $second = AssessmentPhoto::factory()->for($inspection)->for($assessment, 'assessment')->create([
            'organization_id' => $organization->id,
            'position' => 2,
        ]);

        $migration = require database_path('migrations/2026_08_17_000034_remove_assessment_photo_roles.php');
        $migration->down();

        $this->assertTrue(Schema::hasColumns('assessment_photos', ['is_primary', 'report_slot']));
        $this->assertSame(
            ['is_primary' => 1, 'report_slot' => 1],
            (array) DB::table('assessment_photos')->where('id', $first->id)->first(['is_primary', 'report_slot']),
        );
        $this->assertSame(
            ['is_primary' => 0, 'report_slot' => 2],
            (array) DB::table('assessment_photos')->where('id', $second->id)->first(['is_primary', 'report_slot']),
        );
        $this->assertNull(DB::table('assessment_photos')->where('id', $third->id)->value('report_slot'));

        $migration->up();

        $this->assertFalse(Schema::hasColumn('assessment_photos', 'is_primary'));
        $this->assertFalse(Schema::hasColumn('assessment_photos', 'report_slot'));
    }
}
