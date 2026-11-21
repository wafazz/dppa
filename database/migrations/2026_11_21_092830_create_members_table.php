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
        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->string('member_no', 20)->nullable()->unique();
            $table->string('applicant_name', 150);
            $table->string('business_name', 200)->index();
            $table->foreignId('business_category_id')->constrained('business_categories')->restrictOnDelete();
            $table->string('business_address', 500);
            $table->string('phone', 30)->index();
            $table->string('email', 190)->index();
            $table->string('ssm_no', 50)->nullable()->index();
            $table->longText('business_description');
            $table->longText('business_needs');
            $table->longText('search_text')->nullable();
            $table->string('status', 20)->default('pending');
            $table->dateTime('applied_at');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('consent_given_at');
            $table->string('consent_version', 20);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'applied_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
