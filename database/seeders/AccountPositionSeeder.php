<?php

namespace Database\Seeders;

use App\Models\AccountPosition;
use Illuminate\Database\Seeder;

class AccountPositionSeeder extends Seeder
{
    /**
     * Seed starter management positions for staff accounts. The superadmin can rename or add positions later.
     */
    public function run(): void
    {
        $positions = [
            ['level' => 1, 'name_bm' => 'Pengerusi', 'name_en' => 'Chairman'],
            ['level' => 2, 'name_bm' => 'Timbalan Pengerusi', 'name_en' => 'Vice Chairman'],
            ['level' => 3, 'name_bm' => 'Setiausaha', 'name_en' => 'Secretary'],
            ['level' => 4, 'name_bm' => 'Bendahari', 'name_en' => 'Treasurer'],
            ['level' => 5, 'name_bm' => 'Ahli Jawatankuasa', 'name_en' => 'Committee Member'],
        ];

        foreach ($positions as $position) {
            AccountPosition::query()->updateOrCreate(
                ['level' => $position['level']],
                ['name_bm' => $position['name_bm'], 'name_en' => $position['name_en'], 'is_active' => true],
            );
        }
    }
}
