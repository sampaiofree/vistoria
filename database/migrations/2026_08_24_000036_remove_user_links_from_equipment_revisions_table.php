<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_revisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('preparer_id');
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropConstrainedForeignId('approver_id');
            $table->dropConstrainedForeignId('releaser_id');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_revisions', function (Blueprint $table): void {
            $table->foreignId('preparer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('releaser_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
