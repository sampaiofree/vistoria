<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table): void {
            $table->string('defect_code_prefix', 80)
                ->nullable()
                ->after('normalized_tag');

            $table->unique(
                ['organization_id', 'defect_code_prefix'],
                'equipments_org_defect_code_prefix_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table): void {
            $table->dropUnique('equipments_org_defect_code_prefix_unique');
            $table->dropColumn('defect_code_prefix');
        });
    }
};
