<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('inspection_location_maps', function (Blueprint $table): void {
            $table->foreignId('source_uploaded_by')->nullable()->after('source_checksum')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_location_maps', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_uploaded_by');
        });

        Schema::dropIfExists('notifications');
    }
};
