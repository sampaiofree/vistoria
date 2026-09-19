<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('status', 'suspended')
            ->update(['status' => 'inactive']);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('A remoção da suspensão de usuários é irreversível.');
    }
};
