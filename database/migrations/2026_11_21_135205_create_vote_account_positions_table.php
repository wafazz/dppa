<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Management positions chosen when a vote was set up. The people holding them were added to the voter list at that time.
     */
    public function up(): void
    {
        Schema::create('vote_account_positions', function (Blueprint $table): void {
            $table->foreignId('vote_id')->constrained('votes')->cascadeOnDelete();
            $table->foreignId('account_position_id')->constrained('account_positions')->cascadeOnDelete();
            $table->primary(['vote_id', 'account_position_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vote_account_positions');
    }
};
