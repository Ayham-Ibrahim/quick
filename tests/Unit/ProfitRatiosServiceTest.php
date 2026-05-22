<?php

namespace Tests\Unit;

use App\Jobs\RepriceSyncedVariantsJob;
use App\Models\ProfitRatios;
use App\Models\UserManagement\User;
use App\Services\ProfitRatiosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProfitRatiosServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_dispatches_variant_repricing_when_exchange_rate_changes(): void
    {
        Queue::fake();

        $admin = User::create([
            'name' => 'Admin',
            'phone' => '+963900000001',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $exchangeRate = ProfitRatios::create([
            'tag' => ProfitRatios::TAG_EXCHANGE_RATE,
            'ratio_name' => 'سعر صرف الدولار',
            'value' => 15000,
        ]);

        $this->actingAs($admin);

        app(ProfitRatiosService::class)->updateAll([
            [
                'id' => $exchangeRate->id,
                'value' => 15750,
            ],
        ]);

        Queue::assertPushed(RepriceSyncedVariantsJob::class, function (RepriceSyncedVariantsJob $job) {
            return $job->exchangeRate === 15750.0;
        });
    }
}