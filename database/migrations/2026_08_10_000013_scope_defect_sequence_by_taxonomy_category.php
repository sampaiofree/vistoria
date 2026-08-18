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
        $this->backfillDefectCategories();

        Schema::table('defects', function (Blueprint $table): void {
            $table->dropUnique('defects_sequence_unique');
            $table->unique(
                ['organization_id', 'equipment_id', 'defect_category_id', 'sequence_number'],
                'defects_sequence_category_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('defects', function (Blueprint $table): void {
            $table->dropUnique('defects_sequence_category_unique');
            $table->unique(
                ['organization_id', 'equipment_id', 'category', 'sequence_number'],
                'defects_sequence_unique',
            );
        });
    }

    private function backfillDefectCategories(): void
    {
        DB::table('defect_categories')
            ->orderBy('id')
            ->eachById(function (object $category): void {
                $legacyCategory = strtolower((string) $category->code) === 'cv'
                    ? 'civil'
                    : strtolower((string) $category->code);

                DB::table('defects')
                    ->where('organization_id', $category->organization_id)
                    ->whereNull('defect_category_id')
                    ->where('category', $legacyCategory)
                    ->update(['defect_category_id' => $category->id]);
            });
    }
};
