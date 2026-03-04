<?php
namespace Database\Seeders;

use App\Models\Attribute\Attribute;
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
                'name'  => 'اللون',
                'value' => ['أحمر', 'أزرق', 'أخضر', 'أسود', 'أبيض', 'أصفر', 'برتقالي', 'بنفسجي', 'رمادي', 'ذهبي', 'فضي', 'زهري'],
            ],
            [
                'name'  => 'المقاس',
                'value' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '28', '30', '32', '34', '36', '38'],
            ],
            [
                'name'  => 'المادة',
                'value' => ['قطن', 'بوليستر', 'حرير', 'صوف', 'جلد', 'دنيم', 'مخمل', 'نايلون', 'مخلوط'],
            ],
            [
                'name'  => 'النمط',
                'value' => ['سادة', 'مخطط', 'منقط', 'جرافيك', 'مموه'],
            ],
            [
                'name'  => 'النوع',
                'value' => ['رجالي', 'نسائي', 'أطفال'],
            ],
            [
                'name'  => 'الموسم',
                'value' => ['صيفي', 'شتوي', 'ربيعي', 'خريفي'],
            ],
            [
                'name'  => 'الوزن',
                'value' => ['0.5 كغ', '1 كغ', '2 كغ', '5 كغ', '10 كغ', '20 كغ'],
            ],
            [
                'name'  => 'السعة',
                'value' => ['0.5 لتر', '1 لتر', '2 لتر', '5 لتر', '10 لتر', '20 لتر'],
            ],

            [
                'name'  => 'العلامة التجارية',
                'value' => ['Nike', 'Adidas', 'Samsung', 'Apple', 'Xiaomi', 'محلي', 'بدون علامة'],
            ],
            [
                'name'  => 'بلد المنشأ',
                'value' => ['الصين', 'تركيا', 'ألمانيا', 'إيطاليا', 'الولايات المتحدة', 'محلي'],
            ],
            [
                'name'  => 'الحالة',
                'value' => ['جديد', 'مستعمل', 'مُجدد'],
            ],
            [
                'name'  => 'الضمان',
                'value' => ['بدون ضمان', '3 أشهر', '6 أشهر', 'سنة', 'سنتان'],
            ],

            // ===============================
            // سمات ملابس إضافية
            // ===============================
            [
                'name'  => 'نوع القصة',
                'value' => ['Slim Fit', 'Regular Fit', 'Oversized'],
            ],
            [
                'name'  => 'طول الكم',
                'value' => ['قصير', 'طويل', 'بدون أكمام'],
            ],
            [
                'name'  => 'نوع الياقة',
                'value' => ['دائرية', 'V', 'قميص'],
            ],
            [
                'name'  => 'طريقة الإغلاق',
                'value' => ['سحاب', 'أزرار', 'بدون'],
            ],
            [
                'name'  => 'نوع القماش',
                'value' => ['خفيف', 'متوسط', 'سميك'],
            ],
            // ===============================
            // سمات إلكترونيات
            // ===============================
            [
                'name'  => 'سعة التخزين',
                'value' => ['32GB', '64GB', '128GB', '256GB', '512GB', '1TB'],
            ],
            [
                'name'  => 'الذاكرة العشوائية RAM',
                'value' => ['2GB', '4GB', '8GB', '12GB', '16GB'],
            ],
            [
                'name'  => 'حجم الشاشة',
                'value' => ['5 بوصة', '6 بوصة', '6.5 بوصة', '7 بوصة'],
            ],
            [
                'name'  => 'دقة الشاشة',
                'value' => ['HD', 'Full HD', '2K', '4K'],
            ],
            [
                'name'  => 'نوع المعالج',
                'value' => ['Snapdragon', 'Exynos', 'Apple M', 'Intel', 'AMD'],
            ],
            [
                'name'  => 'نظام التشغيل',
                'value' => ['Android', 'iOS', 'Windows', 'macOS', 'Linux'],
            ],
            [
                'name'  => 'نوع البطارية',
                'value' => ['3000mAh', '4000mAh', '5000mAh', '6000mAh'],
            ],

            // ===============================
            // سمات لوجستية
            // ===============================
            [
                'name'  => 'قابل للإرجاع',
                'value' => ['نعم', 'لا'],
            ],
            [
                'name'  => 'قابل للكسر',
                'value' => ['نعم', 'لا'],
            ],
            [
                'name'  => 'درجة التخزين',
                'value' => ['عادي', 'مبرد', 'مجمد'],
            ],
        ];

        foreach ($attributes as $attributeData) {
            $attribute = Attribute::create([
                'name' => $attributeData['name'],
                'slug' => \Illuminate\Support\Str::slug($attributeData['name']),
            ]);

            $attribute->values()->createMany(array_map(function ($value) {
                return [
                    'value' => trim($value),
                    'slug'  => \Illuminate\Support\Str::slug($value),
                ];
            }, $attributeData['value']));

        }
    }
}
