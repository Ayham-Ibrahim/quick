<?php

namespace App\Helpers;

use App\Models\Wallet;

class WalletHelper
{
    public static function generateUniqueWalletCode(): int
    {
        do {
            $code = random_int(10000000, 99999999); // 8 digits
        } while (Wallet::where('wallet_code', $code)->exists());

        return $code;
    }
}
