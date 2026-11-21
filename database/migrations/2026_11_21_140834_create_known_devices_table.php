<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A device is a hash of the browser's user agent. The raw user agent is not stored.
     */
    public function up(): void
    {
        Schema::create('known_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_hash', 64);
            $table->dateTime('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'device_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('known_devices');
    }
};
