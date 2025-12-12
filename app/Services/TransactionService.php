<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Transaction;
use App\Models\UserManagement\Provider;
use Illuminate\Support\Facades\Auth;

class TransactionService
{
    public function getAdminTransactions($request)
    {
        if (
            $request->filled('provider_id') &&
            !Provider::where('id', $request->provider_id)->exists()
        ) {
            return ['error' => 'Provider ID not found'];
        }

        if (
            $request->filled('driver_id') &&
            !Driver::where('id', $request->driver_id)->exists()
        ) {
            return ['error' => 'Driver ID not found'];
        }

        $query = Transaction::with(['provider:id,provider_name', 'driver:id,driver_name'])
            ->when($request->filled('provider_id'), function ($query) use ($request) {
                $query->where('provider_id', $request->provider_id);
            })
            ->when($request->filled('driver_id'), function ($query) use ($request) {
                $query->where('driver_id', $request->driver_id);
            })
            ->latest();

        $totalAmount = (clone $query)->sum('amount');
        $transactions = $query->paginate(10);

        $transactions->through(function ($transaction) {
            return [
                'id'            => $transaction->id,
                'provider_name' => $transaction->provider?->provider_name,
                'driver_name'   => $transaction->driver?->driver_name,
                'amount'        => $transaction->amount,
                'created_at'    => $transaction->created_at,
            ];
        });

        return [
            'transactions' => $transactions,
            'total'        => $totalAmount
        ];
    }


    public function getProviderTransactions()
    {
        $transactions = Transaction::where('provider_id', Auth::id())
            ->with(['driver:id,driver_name', 'driver.wallet:id,owner_id,balance,wallet_code'])
            ->get();

        return $transactions->map(function ($transaction) {
            return [
                'transaction_id' => $transaction->id,
                'driver_name'    => $transaction->driver?->driver_name,
                'driver_wallet'  => $transaction->driver?->wallet ? [
                    'wallet_code' => $transaction->driver->wallet->wallet_code,
                    'balance'     => $transaction->driver->wallet->balance,
                ] : null,
                'amount'         => $transaction->amount,
                'created_at'     => $transaction->created_at,
            ];
        });
    }

    public function getDriverTransactions()
    {
        $transactions = Transaction::where('driver_id', Auth::id())
            ->with(['provider:id,provider_name', 'driver:id,driver_name'])
            ->get();

        return $transactions->map(function ($transaction) {
            return [
                'transaction_id' => $transaction->id,
                'provider_name'  => $transaction->provider?->provider_name,
                'driver_name'    => $transaction->driver?->driver_name,
                'amount'         => $transaction->amount,
                'created_at'     => $transaction->created_at,
            ];
        });
    }

     public function getTransactionDetails(Transaction $transaction)
    {
        // driver
        if (Auth::guard('driver')->check() && $transaction->driver_id == Auth::id()) {

            $transaction->load(['provider', 'driver']);

            return [
                'id'            => $transaction->id,
                'provider_name' => $transaction->provider?->provider_name,
                'driver_name'   => $transaction->driver?->driver_name,
                'amount'        => $transaction->amount,
                'created_at'    => $transaction->created_at,
            ];
        }

        // provider
        if (Auth::guard('provider')->check() && $transaction->provider_id == Auth::id()) {

            $transaction->load(['provider', 'driver.wallet']);

            return [
                'id'             => $transaction->id,
                'provider_name'  => $transaction->provider?->provider_name,
                'driver_wallet'  => $transaction->driver?->wallet ? [
                    'wallet_code' => $transaction->driver->wallet->wallet_code,
                    'balance'     => $transaction->driver->wallet->balance,
                ] : null,
                'amount'         => $transaction->amount,
                'created_at'     => $transaction->created_at,
            ];
        }

        return null;
    }

}
