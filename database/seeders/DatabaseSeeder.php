<?php

namespace Database\Seeders;

use App\Models\User;
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

        User::create([
            'name' => 'Admin',
            'phone' => '+963258741',
            'avatar' => null,
            'v_location' => '123587430',
            'h_location' => '487214545',
            'password' =>  bcrypt('password@123'),
            'is_admin' => 1,
        ]);
    }
}
