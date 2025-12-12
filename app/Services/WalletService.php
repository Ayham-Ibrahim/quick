<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletService
{
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

            $wallet->increment('balance', $data['amount']);

            Transaction::create([
                'provider_id' => Auth::guard('provider')->id(),
                'driver_id'    => $wallet->owner_id,
                'amount'       => $data['amount'],
            ]);

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
