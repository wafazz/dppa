<?php

namespace Database\Seeders;

use App\Models\BusinessCategory;
use Illuminate\Database\Seeder;

class BusinessCategorySeeder extends Seeder
{
    /**
     * Seed the starter list of business categories. Administrators may edit them later.
     */
    public function run(): void
    {
        $categories = [
            ['Perdagangan runcit', 'Retail trade'],
            ['Makanan dan minuman', 'Food and beverage'],
            ['Pembuatan', 'Manufacturing'],
            ['Pembinaan', 'Construction'],
            ['Pengangkutan dan logistik', 'Transport and logistics'],
            ['Pertanian dan perikanan', 'Agriculture and fisheries'],
            ['Perkhidmatan profesional', 'Professional services'],
            ['Teknologi maklumat', 'Information technology'],
            ['Kecantikan dan kesihatan', 'Beauty and health'],
            ['Pendidikan dan latihan', 'Education and training'],
            ['Pelancongan dan perhotelan', 'Tourism and hospitality'],
            ['Hartanah', 'Property'],
            ['Kewangan dan insurans', 'Finance and insurance'],
            ['Lain-lain', 'Others'],
        ];

        foreach ($categories as $index => [$nameBm, $nameEn]) {
            BusinessCategory::query()->updateOrCreate(
                ['name_bm' => $nameBm],
                [
                    'name_en' => $nameEn,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
