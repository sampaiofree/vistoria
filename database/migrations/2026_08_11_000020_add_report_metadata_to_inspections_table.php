<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->string('emission_type', 1)->nullable()->after('report_date');
            $table->text('first_page_text_template')->nullable()->after('emission_type');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->dropColumn(['emission_type', 'first_page_text_template']);
        });
    }
};
