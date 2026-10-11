<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('report_m3_verifier_name', 150)->nullable();
            $table->string('report_m3_reviewer_name', 150)->nullable();
            $table->string('report_m3_releaser_name', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn([
            'report_m3_verifier_name',
            'report_m3_reviewer_name',
            'report_m3_releaser_name',
        ]));
    }
};
