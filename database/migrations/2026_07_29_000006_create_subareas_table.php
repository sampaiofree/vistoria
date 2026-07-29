<?php

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subareas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('area_id');

            $table->string('name', 150);
            $table->string('code', 80)->nullable();
            $table->string('normalized_code', 80)->nullable();
            $table->string('status', 20)
                ->default(RegistrationStatus::Active->value);
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'area_id'],
                'subareas_org_area_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('areas')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'area_id', 'normalized_code'],
                'subareas_org_area_code_unique',
            );

            $table->index(
                ['organization_id', 'area_id', 'status', 'name'],
                'subareas_org_area_status_name_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subareas');
    }
};
