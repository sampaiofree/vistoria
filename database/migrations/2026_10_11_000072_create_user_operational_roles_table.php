<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_operational_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30);
            $table->timestamps();
            $table->unique(['user_id', 'role']);
            $table->index(['role', 'user_id']);
        });

        DB::table('users')
            ->whereIn('account_type', ['member', 'company_admin'])
            ->whereIn('operational_role', ['planner', 'inspector', 'reviewer', 'releaser'])
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                DB::table('user_operational_roles')->insertOrIgnore($users->map(fn ($user): array => [
                    'user_id' => $user->id,
                    'role' => $user->operational_role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_operational_roles');
    }
};
