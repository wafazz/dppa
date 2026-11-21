<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_authenticator_secret')->nullable()->after('business_document_name');
            $table->timestamp('two_factor_authenticator_confirmed_at')->nullable()->after('two_factor_authenticator_secret');
            $table->unsignedBigInteger('two_factor_authenticator_last_step')->nullable()->after('two_factor_authenticator_confirmed_at');
            $table->boolean('two_factor_email_enabled')->default(false)->after('two_factor_authenticator_last_step');
            $table->boolean('two_factor_whatsapp_enabled')->default(false)->after('two_factor_email_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_authenticator_secret',
                'two_factor_authenticator_confirmed_at',
                'two_factor_authenticator_last_step',
                'two_factor_email_enabled',
                'two_factor_whatsapp_enabled',
            ]);
        });
    }
};
