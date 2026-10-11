<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->boolean('is_m3')->default(false);
        });

        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->string('recovery_classification_code', 32)->nullable();
            $table->json('recovery_classification_snapshot')->nullable();
            $table->timestamp('recovery_classified_at')->nullable();
            $table->foreignId('recovery_classified_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recovery_classified_by');
            $table->dropColumn(['recovery_classification_code', 'recovery_classification_snapshot', 'recovery_classified_at']);
        });

        Schema::table('inspections', fn (Blueprint $table) => $table->dropColumn('is_m3'));
    }
};
