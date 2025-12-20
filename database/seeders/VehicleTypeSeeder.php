<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        VehicleType::create(['type' => 'دراجة نارية']);
        VehicleType::create(['type' => 'سيارة']);
        VehicleType::create(['type' => 'شاحنة']);
        VehicleType::create(['type' => 'دراجة هوائية']);
    }
}
