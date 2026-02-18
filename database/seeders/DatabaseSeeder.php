<?php

namespace Database\Seeders;

use App\Helpers\WalletHelper;
use App\Models\UserManagement\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $new_admin= User::create([
            'name' => 'Admin',
            'phone' => '+963258741',
            'avatar' => null,
            'phone_verified_at' => now(),
            'v_location' => '123587430',
            'h_location' => '487214545',
            'password' =>  bcrypt('password@123'),
            'is_admin' => 1,
        ]);
        $admin = User::create([
            'name' => 'new Admin',
            'phone' => '+963939811355',
            'avatar' => null,
            'phone_verified_at' => now(),
            'v_location' => '123587430',
            'h_location' => '487214545',
            'password' =>  bcrypt('password@123'),
            'is_admin' => 1,
        ]);
        User::create([
            'name' => 'new user',
            'phone' => '+963939001111',
            'avatar' => null,
            'phone_verified_at' => now(),
            'v_location' => '123587430',
            'h_location' => '487214545',
            'password' =>  bcrypt('password@123'),
            'is_admin' => 0,
        ]);
        $admin->wallet()->create([
            'wallet_code' => WalletHelper::generateUniqueWalletCode(),
            'balance'     => 0,
        ]);
        $new_admin->wallet()->create([
            'wallet_code' => WalletHelper::generateUniqueWalletCode(),
            'balance'     => 0,
        ]);

        $this->call([
            // CategorySeeder::class,
            // SubCategorySeeder::class,
            VehicleTypeSeeder::class,
            ProfitRatiosSeeder::class
        ]);
    }
}
