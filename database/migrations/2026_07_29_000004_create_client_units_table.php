<?php

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_units', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('client_id');

            $table->string('name', 150);
            $table->string('code', 80)->nullable();
            $table->string('normalized_code', 80)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('address_line', 200)->nullable();
            $table->string('address_number', 30)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->char('country_code', 2)->default('BR');
            $table->string('status', 20)
                ->default(RegistrationStatus::Active->value);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'client_id'],
                'client_units_org_client_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('clients')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'client_id', 'normalized_code'],
                'client_units_org_client_code_unique',
            );

            $table->unique(
                ['organization_id', 'id'],
                'client_units_org_id_unique',
            );

            $table->index(
                ['organization_id', 'client_id', 'status', 'name'],
                'client_units_org_client_status_name_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_units');
    }
};
