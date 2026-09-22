<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table): void {
            $table->string('numero_cliente', 50)->after('client_id');
            $table->string('numero_interno', 50)->after('numero_cliente');
            $table->unique(['organization_id', 'numero_cliente'], 'equipments_org_numero_cliente_unique');
            $table->unique(['organization_id', 'numero_interno'], 'equipments_org_numero_interno_unique');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table): void {
            $table->dropUnique('equipments_org_numero_cliente_unique');
            $table->dropUnique('equipments_org_numero_interno_unique');
            $table->dropColumn(['numero_cliente', 'numero_interno']);
        });
    }
};
