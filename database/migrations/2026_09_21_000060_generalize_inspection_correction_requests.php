<?php

declare(strict_types=1);

use App\Enums\InspectionCorrectionRequestFlow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_correction_requests', function (Blueprint $table): void {
            $table->string('flow', 40)
                ->default(InspectionCorrectionRequestFlow::ReviewerToInspector->value)
                ->after('status');
            $table->foreignId('parent_request_id')
                ->nullable()
                ->after('previous_request_id')
                ->constrained('inspection_correction_requests')
                ->restrictOnDelete();
            $table->renameColumn('reviewer_message', 'request_message');
            $table->renameColumn('inspector_response', 'response_message');
            $table->index(['organization_id', 'inspection_id', 'flow', 'status'], 'correction_requests_flow_status_index');
            $table->index(['organization_id', 'parent_request_id', 'defect_assessment_id', 'flow', 'status'], 'correction_requests_parent_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_correction_requests', function (Blueprint $table): void {
            $table->dropIndex('correction_requests_flow_status_index');
            $table->dropIndex('correction_requests_parent_scope_index');
            $table->dropConstrainedForeignId('parent_request_id');
            $table->renameColumn('request_message', 'reviewer_message');
            $table->renameColumn('response_message', 'inspector_response');
            $table->dropColumn('flow');
        });
    }
};
