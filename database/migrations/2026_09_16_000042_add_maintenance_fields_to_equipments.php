<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep a non-unique index supporting the client foreign key and TAG searches.
        Schema::table('equipments', function (Blueprint $table): void {
            $table->index(['organization_id', 'client_id', 'normalized_tag'], 'equipments_org_client_tag_index');
        });

        foreach (Schema::getIndexes('equipments') as $index) {
            if ($index['unique'] && $index['columns'] === ['organization_id', 'client_id', 'normalized_tag']) {
                Schema::table('equipments', fn (Blueprint $table) => $table->dropUnique($index['name']));
            }
        }

        Schema::table('equipments', function (Blueprint $table): void {
            foreach (['maintenance_plan_code', 'maintenance_item_code', 'area_code', 'subarea_code', 'task_list_group', 'task_list_group_counter'] as $field) {
                $table->string($field, 80)->nullable();
            }
            $table->string('area_name', 180)->nullable();
            $table->string('subarea_name', 180)->nullable();
            // Deliberately includes soft-deleted equipment; legacy rows remain NULL.
            $table->unique(['organization_id', 'maintenance_item_code'], 'equipments_org_maintenance_item_unique');
        });
    }

    public function down(): void
    {
        // Refuse rollback before removing attributes if duplicate TAGs now exist.
        Schema::table('equipments', function (Blueprint $table): void {
            $table->unique(['organization_id', 'client_id', 'normalized_tag'], 'equipments_org_client_tag_unique');
        });
        Schema::table('equipments', function (Blueprint $table): void {
            $table->dropIndex('equipments_org_client_tag_index');
            $table->dropUnique('equipments_org_maintenance_item_unique');
            $table->dropColumn(['maintenance_plan_code', 'maintenance_item_code', 'area_code', 'area_name', 'subarea_code', 'subarea_name', 'task_list_group', 'task_list_group_counter']);
        });
    }
};
