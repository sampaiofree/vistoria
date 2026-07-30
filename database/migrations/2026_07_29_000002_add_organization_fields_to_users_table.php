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

            $table->ulid('public_id')
                ->unique()
                ->after('organization_id');

            $table->string('account_type', 30)
                ->default(UserAccountType::Member->value)
                ->after('password');

            $table->string('status', 20)
                ->default(UserStatus::Active->value)
                ->after('account_type');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('status');

            $table->timestamp('suspended_at')
                ->nullable()
                ->after('last_login_at');

            $table->text('suspension_reason')
                ->nullable()
                ->after('suspended_at');

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'account_type']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['organization_id', 'account_type']);
            $table->dropUnique(['public_id']);

            $table->dropColumn([
                'organization_id',
                'public_id',
                'account_type',
                'status',
                'last_login_at',
                'suspended_at',
                'suspension_reason',
            ]);
        });
    }
};
