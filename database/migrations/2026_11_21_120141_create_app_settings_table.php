<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Secret values are stored encrypted by the application, never in plain text.
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
