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
            $table->unique(['organization_id', 'inspection_id', 'id'], 'defect_assessments_org_inspection_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->dropUnique('defect_assessments_org_inspection_id_unique');
        });
    }
};
