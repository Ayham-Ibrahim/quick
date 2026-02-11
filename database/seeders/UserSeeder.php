<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserManagement\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'new user',
            'phone' => '+963939000005',
            'avatar' => null,
            'v_location' => '123587430',
            'h_location' => '487214545',
            'password' =>  bcrypt('password@123'),
            'is_admin' => 0,
        ]);
    }
}
