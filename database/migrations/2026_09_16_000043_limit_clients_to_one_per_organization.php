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
        $organizations = DB::table('clients')
            ->select('organization_id')
            ->groupBy('organization_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('organization_id');

        if ($organizations->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Não foi possível limitar clientes: as organizações %s possuem mais de um registro, inclusive excluídos logicamente. Corrija a base antes de executar esta migração.',
                $organizations->implode(', '),
            ));
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->unique('organization_id', 'clients_organization_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique('clients_organization_unique');
        });
    }
};
