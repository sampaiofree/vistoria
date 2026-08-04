<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\UserAccountType;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\ViewFirstDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class ViewFirstDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_to_seed_outside_local_and_testing_environments(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->instance('env', 'production');

        try {
            $this->app->make(ViewFirstDemoSeeder::class)->run();
            $this->fail('O seeder deveria recusar a execução em produção.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local e testing', $exception->getMessage());
        } finally {
            $this->app->instance('env', $originalEnvironment);
        }

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_it_seeds_the_complete_view_first_scenario_idempotently_without_touching_other_data(): void
    {
        Storage::fake('equipment_documents');

        $unrelatedOrganization = Organization::factory()->create([
            'document' => '99999999000199',
            'name' => 'Organização preservada',
        ]);

        $this->seed(ViewFirstDemoSeeder::class);

        $organization = Organization::query()
            ->where('document', ViewFirstDemoSeeder::ORGANIZATION_DOCUMENT)
            ->firstOrFail();
        $equipment = Equipment::query()
            ->where('organization_id', $organization->id)
            ->where('normalized_tag', ViewFirstDemoSeeder::EQUIPMENT_TAG)
            ->firstOrFail();
        $previous = $this->inspection(ViewFirstDemoSeeder::PREVIOUS_INSPECTION_SERVICE_ORDER);
        $current = $this->inspection(ViewFirstDemoSeeder::CURRENT_INSPECTION_SERVICE_ORDER);
        $document = EquipmentDocument::query()
            ->where('organization_id', $organization->id)
            ->where('equipment_id', $equipment->id)
            ->where('document_number', ViewFirstDemoSeeder::DOCUMENT_NUMBER)
            ->firstOrFail();

        $inspectionIds = [$previous->id, $current->id];
        $defectIds = Defect::query()
            ->where('equipment_id', $equipment->id)
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all();
        $assessmentIds = DefectAssessment::query()
            ->whereIn('inspection_id', $inspectionIds)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        Storage::disk('equipment_documents')->delete($document->path);
        Storage::disk('equipment_documents')->assertMissing($document->path);

        $this->seed(ViewFirstDemoSeeder::class);

        $this->assertSame('Organização preservada', $unrelatedOrganization->fresh()->name);
        $this->assertSame(1, Organization::query()->whereKey($unrelatedOrganization->id)->count());
        $this->assertSame(1, Organization::query()->where('document', ViewFirstDemoSeeder::ORGANIZATION_DOCUMENT)->count());
        $this->assertSame(2, Inspection::query()->where('equipment_id', $equipment->id)->count());
        $this->assertSame(14, Defect::query()->where('equipment_id', $equipment->id)->count());
        $this->assertSame(27, DefectAssessment::query()->whereIn('inspection_id', $inspectionIds)->count());
        $this->assertSame(2, InspectionReferenceDocument::query()->whereIn('inspection_id', $inspectionIds)->count());
        $this->assertSame(10, InspectionResponsible::query()->whereIn('inspection_id', $inspectionIds)->count());
        $this->assertSame($defectIds, Defect::query()
            ->where('equipment_id', $equipment->id)
            ->orderBy('code')
            ->pluck('id', 'code')
            ->all());
        $this->assertSame($assessmentIds, DefectAssessment::query()
            ->whereIn('inspection_id', $inspectionIds)
            ->orderBy('id')
            ->pluck('id')
            ->all());
        $this->assertSame($previous->id, $this->inspection(ViewFirstDemoSeeder::PREVIOUS_INSPECTION_SERVICE_ORDER)->id);
        $this->assertSame($current->id, $this->inspection(ViewFirstDemoSeeder::CURRENT_INSPECTION_SERVICE_ORDER)->id);
        Storage::disk('equipment_documents')->assertExists($document->path);
    }

    public function test_it_creates_coherent_inspections_assessments_responsibles_and_private_document(): void
    {
        Storage::fake('equipment_documents');

        $this->seed(ViewFirstDemoSeeder::class);

        $demo = User::query()->where('email', 'demo@vistoria.test')->firstOrFail();
        $previous = $this->inspection(ViewFirstDemoSeeder::PREVIOUS_INSPECTION_SERVICE_ORDER);
        $current = $this->inspection(ViewFirstDemoSeeder::CURRENT_INSPECTION_SERVICE_ORDER);

        $this->assertSame(UserAccountType::CompanyAdmin, $demo->account_type);
        $this->assertTrue(Hash::check('password', (string) $demo->password));
        $this->assertSame(InspectionType::Initial, $previous->inspection_type);
        $this->assertSame(InspectionStatus::Released, $previous->status);
        $this->assertSame(InspectionType::Reinspection, $current->inspection_type);
        $this->assertSame(InspectionStatus::InProgress, $current->status);
        $this->assertSame($previous->id, $current->previous_inspection_id);
        $this->assertNotNull($previous->released_at);
        $this->assertNotNull($current->started_at);

        $this->assertSame(13, $previous->defectAssessments()->count());
        $this->assertSame(14, $current->defectAssessments()->count());
        $this->assertSame(13, $current->defectAssessments()
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->count());
        $this->assertSame(1, $current->defectAssessments()
            ->where('status', DefectAssessmentStatus::Draft->value)
            ->count());
        $this->assertSame(13, $current->defectAssessments()
            ->whereNotNull('previous_assessment_id')
            ->count());

        $conditions = $current->defectAssessments()
            ->orderBy('condition')
            ->pluck('condition')
            ->all();
        $this->assertEqualsCanonicalizing([
            DefectAssessmentCondition::Worsened,
            DefectAssessmentCondition::New,
            DefectAssessmentCondition::Unchanged,
            DefectAssessmentCondition::Unchanged,
            DefectAssessmentCondition::Improved,
            DefectAssessmentCondition::Repaired,
            DefectAssessmentCondition::Unchanged,
            DefectAssessmentCondition::Worsened,
            DefectAssessmentCondition::Unchanged,
            DefectAssessmentCondition::Worsened,
            DefectAssessmentCondition::Unchanged,
            DefectAssessmentCondition::Improved,
            DefectAssessmentCondition::Unchanged,
            DefectAssessmentCondition::Worsened,
        ], $conditions);

        $draft = $current->defectAssessments()
            ->where('status', DefectAssessmentStatus::Draft->value)
            ->with('defect')
            ->firstOrFail();
        $this->assertSame(DefectAssessmentCondition::New, $draft->condition);
        $this->assertSame('Desplacamento do cobrimento na base do motor', $draft->defect->title);
        $this->assertNull($draft->previous_assessment_id);

        $repaired = $current->defectAssessments()
            ->where('condition', DefectAssessmentCondition::Repaired->value)
            ->with('defect')
            ->firstOrFail();
        $this->assertSame(DefectAssessmentStatus::Complete, $repaired->status);
        $this->assertSame('Fissura capilar no bloco de fundação', $repaired->defect->title);

        foreach ([$previous, $current] as $inspection) {
            $responsibles = $inspection->responsibles()->get();

            $this->assertCount(count(InspectionResponsibility::cases()), $responsibles);
            $this->assertCount(count(InspectionResponsibility::cases()), $responsibles->pluck('user_id')->unique());
            $this->assertTrue($responsibles->every(fn (InspectionResponsible $responsible): bool => $responsible->is_primary));
            $this->assertSame(
                $demo->id,
                $responsibles->firstWhere('responsibility', InspectionResponsibility::Preparer)?->user_id,
            );
        }

        $document = EquipmentDocument::query()
            ->where('document_number', ViewFirstDemoSeeder::DOCUMENT_NUMBER)
            ->firstOrFail();
        $contents = Storage::disk($document->disk)->get($document->path);

        Storage::disk($document->disk)->assertExists($document->path);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertStringStartsWith('%PDF-1.4', $contents);
        $this->assertSame(strlen($contents), $document->size);
        $this->assertSame(hash('sha256', $contents), $document->checksum);
        $this->assertTrue($document->is_current);
        $this->assertSame(2, $document->equipment
            ->inspections()
            ->whereHas('referenceDocuments', fn ($query) => $query->where('equipment_document_id', $document->id))
            ->count());
    }

    private function inspection(string $serviceOrder): Inspection
    {
        return Inspection::query()
            ->where('service_order', $serviceOrder)
            ->firstOrFail();
    }
}
