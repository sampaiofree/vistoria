<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_revisions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->string('emission_type', 1);
            $table->date('revision_date');

            $table->foreignId('preparer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('releaser_id')->constrained('users')->restrictOnDelete();

            $table->string('preparer_name', 180);
            $table->string('reviewer_name', 180);
            $table->string('approver_name', 180);
            $table->string('releaser_name', 180);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'equipment_id'],
                'equipment_revisions_org_equipment_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipments')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'equipment_id', 'revision_date', 'id'],
                'equipment_revisions_org_equipment_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_revisions');
    }
};
