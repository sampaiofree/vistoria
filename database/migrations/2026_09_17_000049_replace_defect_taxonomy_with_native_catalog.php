<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    // SQLite needs foreign keys disabled before its table-rebuild transaction.
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(function (): void {
                DB::transaction(fn () => $this->replaceTaxonomy());
            });
        } else {
            $this->replaceTaxonomy();
        }
    }

    private function replaceTaxonomy(): void
    {
        $photoFiles = DB::table('assessment_photos')
            ->select(['disk', 'original_path', 'optimized_path', 'thumbnail_path'])
            ->get()
            ->map(fn (object $photo): array => [
                'disk' => (string) ($photo->disk ?: 'inspection_photos'),
                'paths' => array_values(array_filter([
                    $photo->original_path,
                    $photo->optimized_path,
                    $photo->thumbnail_path,
                ], fn (mixed $path): bool => is_string($path) && $path !== '')),
            ])
            ->all();

        DB::transaction(function (): void {
            DB::table('inspection_location_marker_photos')->delete();
            DB::table('inspection_location_markers')->delete();
            DB::table('inspection_location_maps')->delete();
            DB::table('defect_assessment_quantities')->delete();
            DB::table('assessment_photos')->delete();
            DB::table('defect_assessments')->update(['previous_assessment_id' => null]);
            DB::table('defect_assessments')->delete();
            DB::table('defect_relations')->delete();
            DB::table('defects')->delete();
            DB::table('defect_code_sequences')->delete();
        });

        $this->deleteAssessmentPhotoFiles($photoFiles);

        Schema::table('defects', function (Blueprint $table): void {
            $table->dropUnique('defects_sequence_category_unique');
            $table->dropIndex('defects_org_category_status_index');
            $this->dropForeign($table, 'defects_org_category_foreign', 'defect_category_id');
            $table->dropColumn('defect_category_id');
            $table->unique(['organization_id', 'equipment_id', 'category', 'sequence_number'], 'defects_sequence_unique');
            $table->index(['organization_id', 'category', 'status'], 'defects_org_category_status_index');
        });

        Schema::table('defect_code_sequences', function (Blueprint $table): void {
            $table->dropUnique('defect_sequences_scope_category_unique');
            $this->dropForeign($table, 'defect_sequences_org_category_foreign', 'defect_category_id');
            $table->dropColumn('defect_category_id');
        });

        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->dropIndex('assessments_org_classification_index');
            $this->dropForeign($table, 'assessments_org_classification_foreign', 'defect_classification_id');
            $table->dropColumn('defect_classification_id');
            $table->index(['organization_id', 'classification_code'], 'assessments_org_classification_code_index');
        });

        Schema::table('inspection_location_maps', function (Blueprint $table): void {
            $table->dropIndex('location_maps_scope_position_index');
            $this->dropForeign($table, 'location_maps_org_category_foreign', 'defect_category_id');
            $table->dropColumn('defect_category_id');
            $table->string('category', 30);
            $table->index(['organization_id', 'inspection_id', 'category', 'position'], 'location_maps_scope_position_index');
        });

        Schema::drop('defect_category_gut_options');
        Schema::drop('defect_classifications');
        Schema::drop('defect_categories');
    }

    /** @param list<array{disk:string,paths:list<string>}> $photoFiles */
    private function deleteAssessmentPhotoFiles(array $photoFiles): void
    {
        foreach ($photoFiles as $photo) {
            if ($photo['paths'] === []) {
                continue;
            }

            try {
                if (! Storage::disk($photo['disk'])->delete($photo['paths'])) {
                    Log::warning('A limpeza do catálogo removeu a foto do banco, mas não conseguiu remover todos os arquivos físicos.', [
                        'disk' => $photo['disk'],
                        'paths' => $photo['paths'],
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('Falha ao remover arquivos físicos de uma foto durante a limpeza do catálogo.', [
                    'disk' => $photo['disk'],
                    'paths' => $photo['paths'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function dropForeign(Blueprint $table, string $name, string $column): void
    {
        $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['organization_id', $column] : $name);
    }

    public function down(): void
    {
        throw new RuntimeException('A substituição do catálogo e a limpeza dos dados de desenvolvimento são irreversíveis.');
    }
};
