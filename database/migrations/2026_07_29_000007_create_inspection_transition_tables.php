<?php

declare(strict_types=1);

use App\Enums\InspectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->string('status', 40)->default(InspectionStatus::Planned->value);
            $table->date('inspected_on')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('field_completed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('report_generated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('inspection_responsibles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('responsibility', 30);
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->unique(['inspection_id', 'user_id', 'responsibility']);
        });

        Schema::create('inspection_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40);
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_status_histories');
        Schema::dropIfExists('inspection_responsibles');
        Schema::dropIfExists('inspections');
    }
};
