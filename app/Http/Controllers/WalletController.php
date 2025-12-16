<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletRequests\AddBalanceRequest;
use App\Services\WalletService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Request;

class WalletController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }
    // add balance to wallet by provider to driver

    public function addBalance(AddBalanceRequest $request)
    {
        $data = $request->validated();

        $result = $this->walletService->addBalanceToWallet($data);

        if ($result['error']) {
            return $this->error($result['message'], $result['code'] ?? 400);
        }

        return $this->success('تمت إضافة الرصيد بنجاح.', $result['wallet']);
    }


    public function getWallet()
    {
        $wallet = $this->walletService->getWallet();
        return $this->success('تم جلب بيانات المحفظة بنجاح', $wallet);
    }
}
