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
            $table->string('password')->nullable()->change();
            $table->foreignId('member_id')->nullable()->unique()->after('business_document_name')
                ->constrained('members')->nullOnDelete();
            $table->boolean('can_create_votes')->default(false)->after('member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['member_id']);
            $table->dropColumn(['member_id', 'can_create_votes']);
        });
    }
};
