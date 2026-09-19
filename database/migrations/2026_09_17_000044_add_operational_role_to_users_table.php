<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('operational_role', 30)
                ->nullable()
                ->after('account_type');
            $table->index(['organization_id', 'operational_role'], 'users_org_operational_role_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_org_operational_role_index');
            $table->dropColumn('operational_role');
        });
    }
};
