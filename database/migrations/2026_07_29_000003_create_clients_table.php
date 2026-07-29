<?php

use App\Enums\RegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('document', 20)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)
                ->default(RegistrationStatus::Active->value);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'document'],
                'clients_org_document_unique',
            );

            $table->unique(
                ['organization_id', 'id'],
                'clients_org_id_unique',
            );

            $table->index(
                ['organization_id', 'status', 'name'],
                'clients_org_status_name_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
