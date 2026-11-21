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
        Schema::create('votes', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('opens_at')->nullable();
            $table->dateTime('closes_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vote_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vote_id')->constrained('votes')->cascadeOnDelete();
            $table->string('label', 190);
            $table->unsignedSmallInteger('position')->default(0);
        });

        // Who may vote in a vote. Fixed when the vote is created.
        Schema::create('vote_voters', function (Blueprint $table): void {
            $table->foreignId('vote_id')->constrained('votes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['vote_id', 'user_id']);
        });

        // Who has voted. This records that a person voted, never what they chose.
        Schema::create('vote_receipts', function (Blueprint $table): void {
            $table->foreignId('vote_id')->constrained('votes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('cast_at');
            $table->primary(['vote_id', 'user_id']);
        });

        // The choices. A ballot has no account on it, so a choice cannot be traced back to a person.
        Schema::create('vote_ballots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vote_id')->constrained('votes')->cascadeOnDelete();
            $table->foreignId('vote_option_id')->constrained('vote_options')->cascadeOnDelete();
            $table->index(['vote_id', 'vote_option_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vote_ballots');
        Schema::dropIfExists('vote_receipts');
        Schema::dropIfExists('vote_voters');
        Schema::dropIfExists('vote_options');
        Schema::dropIfExists('votes');
    }
};
