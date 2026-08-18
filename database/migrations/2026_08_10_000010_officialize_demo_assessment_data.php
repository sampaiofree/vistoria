<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('is_demo')->default(false)->after('timezone')->index();
        });

        Schema::create('defect_assessment_quantities', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('defect_assessment_id');
            $table->string('description', 180)->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('measurement_value', 16, 4);
            $table->string('measurement_unit', 20);
            $table->unsignedInteger('position')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(
                ['organization_id', 'inspection_id', 'defect_assessment_id'],
                'assessment_quantities_org_assessment_foreign',
            )->references(['organization_id', 'inspection_id', 'id'])
                ->on('defect_assessments')
                ->cascadeOnDelete();

            $table->index(
                ['organization_id', 'defect_assessment_id', 'position'],
                'assessment_quantities_org_assessment_position_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_assessment_quantities');

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['is_demo']);
            $table->dropColumn('is_demo');
        });
    }
};
