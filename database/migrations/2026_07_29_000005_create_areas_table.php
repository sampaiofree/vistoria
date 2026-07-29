<?php

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('client_unit_id');

            $table->string('name', 150);
            $table->string('code', 80)->nullable();
            $table->string('normalized_code', 80)->nullable();
            $table->string('status', 20)
                ->default(RegistrationStatus::Active->value);
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'client_unit_id'],
                'areas_org_unit_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('client_units')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'client_unit_id', 'normalized_code'],
                'areas_org_unit_code_unique',
            );

            $table->unique(
                ['organization_id', 'id'],
                'areas_org_id_unique',
            );

            $table->index(
                ['organization_id', 'client_unit_id', 'status', 'name'],
                'areas_org_unit_status_name_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
