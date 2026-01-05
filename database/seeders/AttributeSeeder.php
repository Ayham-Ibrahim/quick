<?php

namespace Database\Seeders;

use App\Models\Attribute\Attribute;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $attributes = [
            [
                'name' => 'اللون',
                'value' => ['أحمر', 'أزرق', 'أخضر', 'أسود', 'أبيض']
            ],
            [
                'name' => 'المقاس',
                'value' => ['صغير', 'متوسط', 'كبير', 'كبير جداً']
            ],
            [
                'name' => 'المادة',
                'value' => ['قطن', 'بوليستر', 'حرير', 'صوف']
            ]
        ];

        foreach ($attributes as $attributeData) {
            $attribute = Attribute::create([
                'name' => $attributeData['name'],
                'slug' => \Illuminate\Support\Str::slug($attributeData['name']),
            ]);

            
            $attribute->values()->createMany(array_map(function($value) {
                return [
                    'value' => trim($value),
                    'slug' => \Illuminate\Support\Str::slug($value),
                ];
            }, $attributeData['value']));
            
        }
    }
}
