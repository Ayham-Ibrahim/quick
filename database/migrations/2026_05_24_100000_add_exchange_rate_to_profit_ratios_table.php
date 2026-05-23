<?php

use App\Models\ProfitRatios;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!ProfitRatios::query()->where('tag', ProfitRatios::TAG_EXCHANGE_RATE)->exists()) {
            ProfitRatios::query()->create([
                'tag' => ProfitRatios::TAG_EXCHANGE_RATE,
                'ratio_name' => 'سعر صرف الدولار',
                'value' => 15000,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ProfitRatios::query()
            ->where('tag', ProfitRatios::TAG_EXCHANGE_RATE)
            ->delete();
    }
};