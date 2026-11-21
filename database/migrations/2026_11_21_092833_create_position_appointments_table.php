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
        Schema::create('position_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('chamber_position_id')->constrained('chamber_positions')->restrictOnDelete();
            $table->date('term_start');
            $table->date('term_end')->nullable();
            $table->boolean('is_current')->default(false);
            $table->longText('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['chamber_position_id', 'is_current']);
            $table->index(['member_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_appointments');
    }
};
