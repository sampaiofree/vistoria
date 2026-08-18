<?php

use App\Enums\ClassificationProfileStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $demoOrganizationIds = DB::table('organizations')
            ->where('is_demo', true)
            ->pluck('id');

        if ($demoOrganizationIds->isNotEmpty()) {
            DB::table('classification_profiles')
                ->whereIn('organization_id', $demoOrganizationIds)
                ->where('name', 'CIVIL — perfil provisório')
                ->where('procedure_number', 'T000000-S-2PO006')
                ->where('status', ClassificationProfileStatus::Active->value)
                ->update([
                    'status' => ClassificationProfileStatus::Retired->value,
                    'effective_until' => now()->toDateString(),
                    'updated_at' => now(),
                ]);
        }

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['is_demo']);
            $table->dropColumn('is_demo');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('is_demo')->default(false)->after('timezone')->index();
        });
    }
};
