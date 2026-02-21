<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function __construct(protected NotificationService $notificationService)
    {
    }

    // add balance to wallet by provider to driver

    public function addBalanceToWallet(array $data)
    {
        return DB::transaction(function () use ($data) {

            $wallet = Wallet::where('wallet_code', $data['wallet_code'])->first();

            // تأكد أن المالك هو سائق
            if (!$wallet->owner instanceof \App\Models\Driver) {
                return [
                    'error' => true,
                    'message' => 'المحفظة لا تخص سائقًا.',
                    'code' => 400,
                ];
            }

            $driver = $wallet->owner;

            $wallet->increment('balance', $data['amount']);

            $chargedBy = Auth::guard('provider')->check() ? 'provider' : 'admin';

            $this->notificationService->notifyDriverWalletCharged(
                $driver,
                (float) $data['amount'],
                $chargedBy
            );

            if(Auth::guard('provider')->check()){
                Transaction::create([
                'provider_id' => Auth::guard('provider')->id(),
                'driver_id'    => $wallet->owner_id,
                'amount'       => $data['amount'],
            ]);
            }

            return [
                'error' => false,
                'wallet' => $wallet->only(['wallet_code', 'balance']),
            ];
        });
    }


    public function getWallet()
    {
        $user = Auth::user();
        $wallet = $user->wallet;
        return $wallet->only(['wallet_code', 'balance']);
    }
}
