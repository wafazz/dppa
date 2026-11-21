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
     * Records already in the database are marked as notified, so the first scheduled run does not e-mail
     * the whole history.
     */
    public function up(): void
    {
        $now = now();

        Schema::table('members', function (Blueprint $table) {
            $table->dateTime('verification_reminded_at')->nullable()->after('email_verified_at');
            $table->dateTime('announcement_consent_at')->nullable()->after('consent_version');
        });

        DB::table('members')->update(['verification_reminded_at' => $now]);

        Schema::table('position_appointments', function (Blueprint $table) {
            $table->dateTime('term_reminded_at')->nullable();
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dateTime('opened_notified_at')->nullable();
            $table->dateTime('closing_notified_at')->nullable();
            $table->dateTime('results_notified_at')->nullable();
        });

        DB::table('votes')->update([
            'opened_notified_at' => $now,
            'closing_notified_at' => $now,
            'results_notified_at' => $now,
        ]);

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('recipient_email', 190)->nullable()->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('recipient_email');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn(['opened_notified_at', 'closing_notified_at', 'results_notified_at']);
        });

        Schema::table('position_appointments', function (Blueprint $table) {
            $table->dropColumn('term_reminded_at');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['verification_reminded_at', 'announcement_consent_at']);
        });
    }
};
