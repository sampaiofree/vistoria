<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->clearLegacyProfileResults();

        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('gravity')->nullable()->change();
            $table->unsignedSmallInteger('urgency')->nullable()->change();
            $table->unsignedSmallInteger('trend')->nullable()->change();
            $table->unsignedBigInteger('gut_score')->nullable()->change();
            $table->json('gut_snapshot')->nullable()->after('classification_snapshot');
            $table->timestamp('gut_classified_at')->nullable()->after('gut_snapshot');
            $table->foreignId('gut_classified_by')->nullable()->after('gut_classified_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('defect_category_gut_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('defect_category_id');
            $table->string('criterion', 20);
            $table->unsignedSmallInteger('score');
            $table->string('color', 7);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(
                ['organization_id', 'defect_category_id'],
                'category_gut_options_org_category_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('defect_categories')
                ->cascadeOnDelete();
            $table->unique(
                ['organization_id', 'defect_category_id', 'criterion', 'score'],
                'category_gut_options_scope_criterion_score_unique',
            );
            $table->index(
                ['organization_id', 'defect_category_id', 'criterion'],
                'category_gut_options_scope_criterion_index',
            );
        });

        if (Schema::hasColumn('inspections', 'classification_profile_id')) {
            Schema::table('inspections', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('classification_profile_id');
                $table->dropColumn('classification_profile_snapshot');
            });
        }

        Schema::dropIfExists('civil_classification_ranges');
        Schema::dropIfExists('gut_criterion_options');
        Schema::dropIfExists('classification_profiles');
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_category_gut_options');

        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gut_classified_by');
            $table->dropColumn(['gut_snapshot', 'gut_classified_at']);
            $table->unsignedTinyInteger('gravity')->nullable()->change();
            $table->unsignedTinyInteger('urgency')->nullable()->change();
            $table->unsignedTinyInteger('trend')->nullable()->change();
            $table->unsignedInteger('gut_score')->nullable()->change();
        });

        // Restore the columns consumed by the previous classification migration
        // so DatabaseMigrations can roll the full migration history back cleanly.
        if (! Schema::hasColumn('inspections', 'classification_profile_id')) {
            Schema::table('inspections', function (Blueprint $table): void {
                $table->unsignedBigInteger('classification_profile_id')->nullable()->after('equipment_id');
                $table->json('classification_profile_snapshot')->nullable()->after('classification_profile_id');
            });
        }
    }

    private function clearLegacyProfileResults(): void
    {
        if (! Schema::hasTable('defect_assessments')) {
            return;
        }

        DB::table('defect_assessments')
            ->select(['id', 'classification_snapshot'])
            ->whereNotNull('classification_snapshot')
            ->orderBy('id')
            ->get()
            ->each(function (object $assessment): void {
                $snapshot = is_string($assessment->classification_snapshot)
                    ? json_decode($assessment->classification_snapshot, true)
                    : $assessment->classification_snapshot;

                if (! is_array($snapshot) || ! array_key_exists('profile_id', $snapshot)) {
                    return;
                }

                DB::table('defect_assessments')
                    ->where('id', $assessment->id)
                    ->update([
                        'gravity' => null,
                        'urgency' => null,
                        'trend' => null,
                        'gut_score' => null,
                        'classification_code' => null,
                        'classification_priority' => null,
                        'deadline_months' => null,
                        'recommended_due_date' => null,
                        'classification_snapshot' => null,
                        'classified_at' => null,
                        'classified_by' => null,
                    ]);
            });
    }
};
