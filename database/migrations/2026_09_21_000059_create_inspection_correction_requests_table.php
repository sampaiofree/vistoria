<?php

declare(strict_types=1);

use App\Enums\InspectionCorrectionRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_id')->constrained()->restrictOnDelete();
            $table->foreignId('defect_assessment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('previous_request_id')->nullable()->constrained('inspection_correction_requests')->restrictOnDelete();
            $table->string('status', 20)->default(InspectionCorrectionRequestStatus::Marked->value);
            $table->text('reviewer_message');
            $table->text('inspector_response')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('addressed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('addressed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'inspection_id', 'status'], 'correction_requests_inspection_status_index');
            $table->index(['organization_id', 'defect_assessment_id', 'status'], 'correction_requests_assessment_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_correction_requests');
    }
};
