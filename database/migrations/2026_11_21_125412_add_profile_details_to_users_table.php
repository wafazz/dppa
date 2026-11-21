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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('address_line1', 190)->nullable()->after('avatar_path');
            $table->string('address_line2', 190)->nullable()->after('address_line1');
            $table->string('postcode', 5)->nullable()->after('address_line2');
            $table->string('city', 100)->nullable()->after('postcode');
            $table->string('state', 60)->nullable()->after('city');
            $table->string('business_name', 190)->nullable()->after('state');
            $table->string('ssm_no', 60)->nullable()->after('business_name');
            $table->foreignId('business_category_id')->nullable()->after('ssm_no')
                ->constrained('business_categories')->nullOnDelete();
            $table->string('business_phone', 30)->nullable()->after('business_category_id');
            $table->timestamp('business_verified_at')->nullable()->after('business_phone');
            $table->foreignId('business_verified_by_user_id')->nullable()->after('business_verified_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['business_category_id']);
            $table->dropForeign(['business_verified_by_user_id']);
            $table->dropColumn([
                'address_line1',
                'address_line2',
                'postcode',
                'city',
                'state',
                'business_name',
                'ssm_no',
                'business_category_id',
                'business_phone',
                'business_verified_at',
                'business_verified_by_user_id',
            ]);
        });
    }
};
