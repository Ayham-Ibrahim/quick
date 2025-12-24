<?php

namespace Database\Seeders;

use App\Models\Complaint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComplaintSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // طريقة 1: استخدام الـ Model Factory (إذا كان لديك Factory)
        // Complaint::factory()->count(20)->create([
        //     'user_id' => 1,
        //     'image' => null,
        // ]);

        // طريقة 2: استخدام الـ Model مباشرة
        $complaints = [
            [
                'user_id' => 1,
                'content' => 'شكوى حول تأخر توصيل الطلب لمدة تتجاوز 3 أيام عن الموعد المحدد',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => 'جودة المنتج لا تتوافق مع المواصفات المعلنة على الموقع',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => 'مشكلة في فاتورة الشراء تحتوي على أخطاء في الأسعار',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => 'دعم العملاء لا يستجيب لاستفساراتي منذ أكثر من 48 ساعة',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => 'المنتج وصل معيب ويحتاج إلى استبدال فوري',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => 'مشكلة في حساب المستخدم لا يمكنني الدخول إليه',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => 'الموقع الإلكتروني يعمل ببطء شديد أثناء عملية الشراء',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => 'طلب إلغاء طلبية لم يتم معالجته رغم مرور 5 أيام عمل',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => 'سعر المنتج على الموقع يختلف عن السعر في عربة التسوق',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => 'مشكلة في عملية الدفع لا يتم قبول بطاقتي الائتمانية',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($complaints as $complaint) {
            Complaint::create($complaint);
        }
    }
}
