<?php

namespace Database\Seeders;

use App\Models\Categories\SubCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $image = "/storage/Category/5vlJr5kvgzJgUn3cjic9dSo4NBLesldX.jpg";

        $subCategories = [
            // المطاعم = 1
            ['name' => 'وجبات سريعة', 'category_id' => 1, 'image' => $image],
            ['name' => 'مقاهي', 'category_id' => 1, 'image' => $image],
            ['name' => 'مطاعم شعبية', 'category_id' => 1, 'image' => $image],

            // المتاجر الإلكترونية = 2
            ['name' => 'إلكترونيات', 'category_id' => 2, 'image' => $image],
            ['name' => 'مستلزمات منزلية', 'category_id' => 2, 'image' => $image],
            ['name' => 'إكسسوارات', 'category_id' => 2, 'image' => $image],

            // الأزياء والموضة = 3
            ['name' => 'ملابس رجالية', 'category_id' => 3, 'image' => $image],
            ['name' => 'ملابس نسائية', 'category_id' => 3, 'image' => $image],
            ['name' => 'أحذية', 'category_id' => 3, 'image' => $image],

            // الأجهزة الكهربائية = 4
            ['name' => 'أجهزة منزلية', 'category_id' => 4, 'image' => $image],
            ['name' => 'هواتف ذكية', 'category_id' => 4, 'image' => $image],
            ['name' => 'ملحقات كهربائية', 'category_id' => 4, 'image' => $image],

            // السوبر ماركت = 5
            ['name' => 'أطعمة معلبة', 'category_id' => 5, 'image' => $image],
            ['name' => 'خضروات وفواكه', 'category_id' => 5, 'image' => $image],
            ['name' => 'مشروبات', 'category_id' => 5, 'image' => $image],

            // الصحة والجمال = 6
            ['name' => 'عطور', 'category_id' => 6, 'image' => $image],
            ['name' => 'مستحضرات تجميل', 'category_id' => 6, 'image' => $image],
            ['name' => 'منتجات العناية', 'category_id' => 6, 'image' => $image],

        ];

        foreach ($subCategories as $sub) {
            SubCategory::create($sub);
        }
    }
}
