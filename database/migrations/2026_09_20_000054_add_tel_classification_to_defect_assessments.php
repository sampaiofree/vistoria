<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('tel_score')->nullable()->after('gut_score');
            $table->json('tel_snapshot')->nullable()->after('gut_snapshot');
            $table->timestamp('tel_classified_at')->nullable()->after('gut_classified_at');
            $table->foreignId('tel_classified_by')->nullable()->after('gut_classified_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tel_classified_by');
            $table->dropColumn(['tel_score', 'tel_snapshot', 'tel_classified_at']);
        });
    }
};
