<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_documents', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'id'],
                'equipment_documents_org_id_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('equipment_documents', function (Blueprint $table): void {
            $table->dropUnique('equipment_documents_org_id_unique');
        });
    }
};
