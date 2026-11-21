<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed reference data. Accounts are created with `php artisan pa:create-user`,
     * so no credentials are stored in source control.
     */
    public function run(): void
    {
        $this->call([
            ChamberPositionSeeder::class,
            BusinessCategorySeeder::class,
            AccountPositionSeeder::class,
        ]);
    }
}
