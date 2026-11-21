<?php

namespace Database\Seeders;

use App\Models\ChamberPosition;
use Illuminate\Database\Seeder;

class ChamberPositionSeeder extends Seeder
{
    /**
     * Seed the initial chamber positions. Re-running updates names without duplicating rows.
     */
    public function run(): void
    {
        $positions = [
            ['name_bm' => 'Presiden', 'name_en' => 'President'],
            ['name_bm' => 'Timbalan Presiden', 'name_en' => 'Deputy President'],
            ['name_bm' => 'Setiausaha', 'name_en' => 'Secretary'],
            ['name_bm' => 'Bendahari', 'name_en' => 'Treasurer'],
            ['name_bm' => 'Ahli Jawatankuasa', 'name_en' => 'Committee Member'],
            ['name_bm' => 'Ahli Biasa', 'name_en' => 'Ordinary Member'],
        ];

        foreach ($positions as $index => $position) {
            ChamberPosition::query()->updateOrCreate(
                ['name_bm' => $position['name_bm']],
                [
                    'name_en' => $position['name_en'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
