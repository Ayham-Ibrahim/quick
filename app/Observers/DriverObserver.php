<?php

namespace App\Observers;

use App\Helpers\WalletHelper;
use App\Models\Driver;
use App\Models\Wallet;

class DriverObserver
{
    /**
     * Handle the Driver "created" event.
     */
    public function created(Driver $driver): void
    {
        // إنشاء محفظة تلقائيًا عند تسجيل سائق جديد
        $driver->wallet()->create([
            'wallet_code' => WalletHelper::generateUniqueWalletCode(),
            'balance'     => 0,
        ]);
    }

    /**
     * Handle the Driver "updated" event.
     */
    public function updated(Driver $driver): void
    {
        //
    }

    /**
     * Handle the Driver "deleted" event.
     */
    public function deleted(Driver $driver): void
    {
        //
    }

    /**
     * Handle the Driver "restored" event.
     */
    public function restored(Driver $driver): void
    {
        //
    }

    /**
     * Handle the Driver "force deleted" event.
     */
    public function forceDeleted(Driver $driver): void
    {
        //
    }
}
