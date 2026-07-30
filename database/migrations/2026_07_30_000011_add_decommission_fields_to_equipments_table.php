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
            $table->timestamp('decommissioned_at')->nullable()->after('status');
            $table->foreignId('decommissioned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('decommissioned_at');
            $table->text('decommission_reason')->nullable()->after('decommissioned_by');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('decommissioned_by');
            $table->dropColumn([
                'decommissioned_at',
                'decommission_reason',
            ]);
        });
    }
};
