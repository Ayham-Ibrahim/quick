<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ForceLogoutAll extends Command
{
    protected $signature   = 'auth:force-logout-all 
                                {--type= : نوع محدد (user/provider/store_manager/driver) أو اتركه فارغاً للكل}';

    protected $description = 'إجبار جميع المستخدمين على تسجيل الخروج وتجديد FCM tokens';

    public function handle()
    {
        $type   = $this->option('type');
        $tables = $this->getTables($type);

        if (empty($tables)) {
            $this->error('نوع غير صحيح. الأنواع المتاحة: user, provider, store_manager, driver');
            return 1;
        }

        // 1. احذف كل الـ tokens
        $tokenCount = PersonalAccessToken::count();
        PersonalAccessToken::truncate();
        $this->info("✅ تم حذف {$tokenCount} token");

        // 2. ارفع الـ token_version لكل الجداول المطلوبة
        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            DB::table($table)->update([
                'token_version' => DB::raw('token_version + 1')
            ]);
            $this->info("✅ {$table}: تم تحديث {$count} مستخدم");
        }

        $this->newLine();
        $this->info('🎉 تم إجبار جميع المستخدمين على تسجيل الخروج بنجاح');
        $this->info('سيُطلب من كل مستخدم تسجيل الدخول من جديد عند فتح التطبيق');

        return 0;
    }

    private function getTables(?string $type): array
    {
        $map = [
            'user'          => ['users'],
            'provider'      => ['providers'],
            'store_manager' => ['stores'],
            'driver'        => ['drivers'],
        ];

        // إذا لم يحدد نوع → كل الجداول
        if (!$type) {
            return ['users', 'providers', 'stores', 'drivers'];
        }

        return $map[$type] ?? [];
    }
}
