<?php

namespace Database\Seeders;

use App\Models\ProfitRatios;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProfitRatiosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'tag' => 'order_profit_percentage',
                'ratio_name' => 'نسبة الربح من كل طلبية من المتاجر',
                'value' => 10,
            ],
            [
                'tag' => 'delivery_profit_per_ride_bike',
                'ratio_name' => 'نسبة الربح من كل توصيل من السائق (دراجة هوائية)',
                'value' => 5,
            ],
            [
                'tag' => 'delivery_profit_per_ride_motorbike',
                'ratio_name' => 'نسبة الربح من كل توصيل من السائق (دراجة نارية)',
                'value' => 5,
            ],
            [
                'tag' => 'km_price',
                'ratio_name' => 'سعر الكيلو متر',
                'value' => 5000,
            ],
            [
                'tag' => 'minimum_order_value',
                'ratio_name' => 'الحد الأدنى لتوصيل الطلبية',
                'value' => 3000,
            ],
        ];

        foreach ($data as $item) {
            ProfitRatios::create($item);
        }
    }
}
