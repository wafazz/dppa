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
     * Fee frequencies become yearly, monthly or lifetime. Invoices keep a text period key: "2027" for a year,
     * "2027-03" for a month, and "lifetime" for a one-off charge.
     */
    public function up(): void
    {
        DB::table('fee_types')->where('frequency', 'annual')->update(['frequency' => 'yearly']);
        DB::table('fee_types')->where('frequency', 'one_time')->update(['frequency' => 'lifetime']);

        Schema::table('membership_invoices', function (Blueprint $table) {
            $table->string('period_key', 10)->nullable()->after('fee_type_id');
        });

        DB::table('membership_invoices')->where('period_year', 0)->update(['period_key' => 'lifetime']);
        DB::table('membership_invoices')->where('period_year', '>', 0)->update([
            'period_key' => DB::raw('CAST(period_year AS CHAR)'),
        ]);

        Schema::table('membership_invoices', function (Blueprint $table) {
            // The foreign key on member_id needs its own index before the composite unique index can go.
            $table->index('member_id');
        });

        Schema::table('membership_invoices', function (Blueprint $table) {
            $table->dropUnique(['member_id', 'fee_type_id', 'period_year']);
            $table->dropColumn('period_year');
            $table->unique(['member_id', 'fee_type_id', 'period_key']);
        });

        DB::statement('ALTER TABLE membership_invoices MODIFY period_key VARCHAR(10) NOT NULL');
    }

    /**
     * Reverse the migrations. Monthly periods cannot be returned to a year, so they fall back to the year.
     */
    public function down(): void
    {
        DB::table('fee_types')->where('frequency', 'yearly')->update(['frequency' => 'annual']);
        DB::table('fee_types')->where('frequency', 'monthly')->update(['frequency' => 'annual']);
        DB::table('fee_types')->where('frequency', 'lifetime')->update(['frequency' => 'one_time']);

        Schema::table('membership_invoices', function (Blueprint $table) {
            $table->unsignedSmallInteger('period_year')->default(0)->after('fee_type_id');
        });

        DB::table('membership_invoices')->where('period_key', 'lifetime')->update(['period_year' => 0]);
        DB::table('membership_invoices')->where('period_key', '!=', 'lifetime')->update([
            'period_year' => DB::raw('CAST(LEFT(period_key, 4) AS UNSIGNED)'),
        ]);

        Schema::table('membership_invoices', function (Blueprint $table) {
            $table->dropUnique(['member_id', 'fee_type_id', 'period_key']);
            $table->dropColumn('period_key');
            $table->unique(['member_id', 'fee_type_id', 'period_year']);
        });
    }
};
