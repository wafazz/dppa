<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing accounts with the old "admin" role become management admins, and their access role becomes committee.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_type', 20)->default('staff')->index();
            $table->boolean('can_invite')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('account_position_id')->nullable()->constrained('account_positions')->nullOnDelete();
        });

        DB::table('users')->where('role', 'admin')->update([
            'account_type' => 'admin',
            'role' => 'committee',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['account_position_id']);
            $table->dropColumn(['account_type', 'can_invite', 'is_active', 'account_position_id']);
        });
    }
};
