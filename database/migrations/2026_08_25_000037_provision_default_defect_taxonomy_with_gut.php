<?php

declare(strict_types=1);

use App\Actions\Classification\ProvisionDefaultDefectTaxonomy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organizations')->orderBy('id')->eachById(function (object $organization): void {
            app(ProvisionDefaultDefectTaxonomy::class)->handle((int) $organization->id);
        });
    }

    public function down(): void
    {
        // The defaults may have been edited after provisioning; preserve organization data.
    }
};
