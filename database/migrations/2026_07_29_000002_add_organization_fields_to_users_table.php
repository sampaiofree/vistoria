<?php

use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('account_type', 30)
                ->default(UserAccountType::Member->value)
                ->after('password');

            $table->string('status', 20)
                ->default(UserStatus::Active->value)
                ->after('account_type');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');

            $table->dropColumn([
                'account_type',
                'status',
                'last_login_at',
            ]);
        });
    }
};
