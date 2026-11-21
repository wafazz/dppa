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
            $table->timestamp('business_submitted_at')->nullable()->after('business_verified_by_user_id');
            $table->string('business_document_path')->nullable()->after('business_submitted_at');
            $table->string('business_document_name', 190)->nullable()->after('business_document_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['business_submitted_at', 'business_document_path', 'business_document_name']);
        });
    }
};
