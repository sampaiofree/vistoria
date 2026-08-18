<?php

declare(strict_types=1);

use App\Enums\ClassificationProfileStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classification_profiles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->string('category', 30)->default('civil');
            $table->string('procedure_number', 150)->nullable();
            $table->string('procedure_revision', 50)->nullable();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default(ClassificationProfileStatus::Draft->value);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'client_id', 'name', 'version'], 'classification_profiles_scope_version_unique');
            $table->index(['organization_id', 'client_id', 'status'], 'classification_profiles_scope_status_index');
        });

        Schema::create('gut_criterion_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classification_profile_id')->constrained()->cascadeOnDelete();
            $table->string('criterion', 20);
            $table->unsignedTinyInteger('score');
            $table->string('label', 150);
            $table->text('description');
            $table->unsignedTinyInteger('position');
            $table->timestamps();
            $table->unique(['classification_profile_id', 'criterion', 'score'], 'gut_options_profile_criterion_score_unique');
        });

        Schema::create('civil_classification_ranges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classification_profile_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('label', 150);
            $table->unsignedInteger('min_score')->nullable();
            $table->unsignedInteger('max_score')->nullable();
            $table->unsignedTinyInteger('priority_order');
            $table->unsignedInteger('deadline_months')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['classification_profile_id', 'code'], 'civil_ranges_profile_code_unique');
            $table->unique(['classification_profile_id', 'priority_order'], 'civil_ranges_profile_priority_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civil_classification_ranges');
        Schema::dropIfExists('gut_criterion_options');
        Schema::dropIfExists('classification_profiles');
    }
};
