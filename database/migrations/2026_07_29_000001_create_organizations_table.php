<?php

use App\Enums\OrganizationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('document', 20)
                ->nullable()
                ->unique();
            $table->string('timezone', 64)
                ->default('America/Sao_Paulo');
            $table->string('status', 20)
                ->default(OrganizationStatus::Active->value);
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
