<?php

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
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default(InspectionStatus::Planned->value);
            $table->timestamps();
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('inspection_responsibles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inspection_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('responsibility', 30);
            $table->boolean('is_primary')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign(
                ['organization_id', 'inspection_id'],
                'inspection_responsibles_org_inspection_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();
            $table->unique(['inspection_id', 'user_id', 'responsibility'], 'inspection_responsibles_unique');
            $table->index(['organization_id', 'inspection_id', 'responsibility'], 'inspection_responsibles_org_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_responsibles');
        Schema::dropIfExists('inspections');
    }
};
