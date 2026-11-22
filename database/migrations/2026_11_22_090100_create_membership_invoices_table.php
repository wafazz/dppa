<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One invoice per member, fee and year. One-time fees use year 0. The amount is copied from the fee type when
     * the invoice is issued, so later fee changes never alter an invoice already sent.
     */
    public function up(): void
    {
        Schema::create('membership_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
            $table->unsignedSmallInteger('period_year')->default(0);
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('paid_cents')->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->date('due_on');
            $table->dateTime('issued_at');
            $table->dateTime('waived_at')->nullable();
            $table->string('waived_reason', 500)->nullable();
            $table->dateTime('reminded_at')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'fee_type_id', 'period_year']);
            $table->index(['status', 'due_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_invoices');
    }
};
