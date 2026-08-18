<?php

declare(strict_types=1);

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->string('status', 20)->default(RegistrationStatus::Active->value);
            $table->unsignedInteger('position')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'defect_categories_org_code_unique');
            $table->unique(['organization_id', 'id'], 'defect_categories_org_id_unique');
            $table->index(['organization_id', 'status', 'position'], 'defect_categories_org_status_position_index');
        });

        Schema::create('defect_classifications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('defect_category_id');
            $table->string('code', 30);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default(RegistrationStatus::Active->value);
            $table->unsignedInteger('position')->default(1);
            $table->unsignedInteger('severity_rank')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(
                ['organization_id', 'defect_category_id'],
                'defect_classifications_org_category_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('defect_categories')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'defect_category_id', 'code'],
                'defect_classifications_scope_code_unique',
            );
            $table->unique(['organization_id', 'id'], 'defect_classifications_org_id_unique');
            $table->index(
                ['organization_id', 'defect_category_id', 'status', 'position'],
                'defect_classifications_scope_status_position_index',
            );
        });

        Schema::table('defects', function (Blueprint $table): void {
            $table->unsignedBigInteger('defect_category_id')->nullable()->after('organization_id');

            $table->foreign(
                ['organization_id', 'defect_category_id'],
                'defects_org_category_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('defect_categories')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'defect_category_id', 'status'],
                'defects_org_category_status_index',
            );
        });

        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->unsignedBigInteger('defect_classification_id')->nullable()->after('defect_id');

            $table->foreign(
                ['organization_id', 'defect_classification_id'],
                'assessments_org_classification_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('defect_classifications')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'defect_classification_id'],
                'assessments_org_classification_index',
            );
        });

        Schema::table('defect_code_sequences', function (Blueprint $table): void {
            $table->unsignedBigInteger('defect_category_id')->nullable()->after('equipment_id');

            $table->foreign(
                ['organization_id', 'defect_category_id'],
                'defect_sequences_org_category_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('defect_categories')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'equipment_id', 'defect_category_id'],
                'defect_sequences_scope_category_unique',
            );
        });

        $this->provisionCivilTaxonomy();
        $this->backfillLegacyReferences();
    }

    public function down(): void
    {
        Schema::table('defect_code_sequences', function (Blueprint $table): void {
            $table->dropUnique('defect_sequences_scope_category_unique');
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['organization_id', 'defect_category_id']);
            } else {
                $table->dropForeign('defect_sequences_org_category_foreign');
            }
            $table->dropColumn('defect_category_id');
        });

        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->dropIndex('assessments_org_classification_index');
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['organization_id', 'defect_classification_id']);
            } else {
                $table->dropForeign('assessments_org_classification_foreign');
            }
            $table->dropColumn('defect_classification_id');
        });

        Schema::table('defects', function (Blueprint $table): void {
            $table->dropIndex('defects_org_category_status_index');
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['organization_id', 'defect_category_id']);
            } else {
                $table->dropForeign('defects_org_category_foreign');
            }
            $table->dropColumn('defect_category_id');
        });

        Schema::dropIfExists('defect_classifications');
        Schema::dropIfExists('defect_categories');
    }

    private function provisionCivilTaxonomy(): void
    {
        DB::table('organizations')->orderBy('id')->eachById(function (object $organization): void {
            $categoryId = DB::table('defect_categories')
                ->where('organization_id', $organization->id)
                ->where('code', 'CV')
                ->value('id');

            if ($categoryId === null) {
                $categoryId = DB::table('defect_categories')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'organization_id' => $organization->id,
                    'name' => 'CIVIL',
                    'code' => 'CV',
                    'description' => 'Avarias relacionadas aos elementos civis.',
                    'status' => RegistrationStatus::Active->value,
                    'position' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ([
                ['code' => 'CV-1', 'name' => 'CV-1', 'position' => 1, 'severity_rank' => 1],
                ['code' => 'CV-2', 'name' => 'CV-2', 'position' => 2, 'severity_rank' => 2],
                ['code' => 'CV-3', 'name' => 'CV-3', 'position' => 3, 'severity_rank' => 3],
                ['code' => 'CV-4', 'name' => 'CV-4', 'position' => 4, 'severity_rank' => 4],
                ['code' => 'CV-5', 'name' => 'CV-5', 'position' => 5, 'severity_rank' => 5],
            ] as $classification) {
                DB::table('defect_classifications')->updateOrInsert(
                    [
                        'organization_id' => $organization->id,
                        'defect_category_id' => $categoryId,
                        'code' => $classification['code'],
                    ],
                    array_merge($classification, [
                        'public_id' => (string) Str::ulid(),
                        'organization_id' => $organization->id,
                        'defect_category_id' => $categoryId,
                        'description' => null,
                        'status' => RegistrationStatus::Active->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]),
                );
            }
        });
    }

    private function backfillLegacyReferences(): void
    {
        DB::table('defect_categories')->orderBy('id')->eachById(function (object $category): void {
            DB::table('defects')
                ->where('organization_id', $category->organization_id)
                ->whereNull('defect_category_id')
                ->where('category', strtolower($category->code) === 'cv' ? 'civil' : strtolower($category->code))
                ->update(['defect_category_id' => $category->id]);

            DB::table('defect_code_sequences')
                ->where('organization_id', $category->organization_id)
                ->whereNull('defect_category_id')
                ->where('category', strtolower($category->code) === 'cv' ? 'civil' : strtolower($category->code))
                ->update(['defect_category_id' => $category->id]);
        });

        DB::table('defect_classifications')->orderBy('id')->eachById(function (object $classification): void {
            DB::table('defect_assessments')
                ->where('organization_id', $classification->organization_id)
                ->whereNull('defect_classification_id')
                ->where('classification_code', $classification->code)
                ->update(['defect_classification_id' => $classification->id]);
        });
    }
};
