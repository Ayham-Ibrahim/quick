<?php

namespace Database\Seeders;

use App\Helpers\WalletHelper;
use App\Models\UserManagement\User;
use Illuminate\Database\Seeder;

class NewAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'new Admin',
            'phone' => '+963955186181',
            'avatar' => null,
            'phone_verified_at' => now(),
            'v_location' => '123587430',
            'h_location' => '487214545',
            'password' =>  bcrypt('ro&uite&eryt'),
            'is_admin' => 1,
        ]);
        $admin->wallet()->create([
            'wallet_code' => WalletHelper::generateUniqueWalletCode(),
            'balance'     => 0,
        ]);
    }
}
