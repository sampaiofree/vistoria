<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_status_histories', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('inspection_id');

            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at');

            $table->foreign(
                ['organization_id', 'inspection_id'],
                'inspection_histories_org_inspection_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'inspection_id', 'created_at'],
                'inspection_histories_org_inspection_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_status_histories');
    }
};
