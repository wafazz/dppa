<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A fee now has one price for each period it offers: monthly, yearly and lifetime. A price left empty means
     * that period is not offered. Members can also report a payment they made, for an administrator to confirm.
     */
    public function up(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            $table->unsignedInteger('monthly_cents')->nullable()->after('description');
            $table->unsignedInteger('yearly_cents')->nullable()->after('monthly_cents');
            $table->unsignedInteger('lifetime_cents')->nullable()->after('yearly_cents');
        });

        DB::table('fee_types')->where('frequency', 'monthly')->update(['monthly_cents' => DB::raw('amount_cents')]);
        DB::table('fee_types')->where('frequency', 'yearly')->update(['yearly_cents' => DB::raw('amount_cents')]);
        DB::table('fee_types')->where('frequency', 'lifetime')->update(['lifetime_cents' => DB::raw('amount_cents')]);

        Schema::table('fee_types', function (Blueprint $table) {
            $table->dropColumn(['amount_cents', 'frequency']);
        });

        Schema::create('payment_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_invoice_id')->constrained('membership_invoices')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('method', 30);
            $table->string('reference', 120)->nullable();
            $table->date('paid_on');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('reject_reason', 500)->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations. Fees with several prices keep the yearly one.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_claims');

        Schema::table('fee_types', function (Blueprint $table) {
            $table->unsignedInteger('amount_cents')->default(0)->after('description');
            $table->string('frequency', 20)->default('yearly')->after('amount_cents');
        });

        DB::table('fee_types')->whereNotNull('yearly_cents')->update([
            'amount_cents' => DB::raw('yearly_cents'), 'frequency' => 'yearly',
        ]);
        DB::table('fee_types')->whereNull('yearly_cents')->whereNotNull('monthly_cents')->update([
            'amount_cents' => DB::raw('monthly_cents'), 'frequency' => 'monthly',
        ]);
        DB::table('fee_types')->whereNull('yearly_cents')->whereNull('monthly_cents')->update([
            'amount_cents' => DB::raw('COALESCE(lifetime_cents, 0)'), 'frequency' => 'lifetime',
        ]);

        Schema::table('fee_types', function (Blueprint $table) {
            $table->dropColumn(['monthly_cents', 'yearly_cents', 'lifetime_cents']);
        });
    }
};
