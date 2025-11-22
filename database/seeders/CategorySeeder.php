<?php

namespace Database\Seeders;

use App\Models\Categories\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $image = "/storage/Category/5vlJr5kvgzJgUn3cjic9dSo4NBLesldX.jpg";

        $categories = [
            ['name' => 'مواد غذائية ', 'image' => $image],
            ['name' => 'الكترونيات', 'image' => $image],
            ['name' => 'ملابس', 'image' => $image],
            ['name' => 'الأجهزة الكهربائية', 'image' => $image],
            ['name' => 'معجنات', 'image' => $image],
            ['name' => 'الصحة والجمال', 'image' => $image],
        ];
        
        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
