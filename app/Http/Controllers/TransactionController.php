<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\UserManagement\Provider;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    protected $service;

    public function __construct(TransactionService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        //  Admin
        if (Auth::user()?->is_admin) {
            $result = $this->service->getAdminTransactions($request);

            if (isset($result['error'])) {
                return $this->error($result['error'], 404);
            }

            return $this->paginateWithData(
                $result['transactions'],
                ['total' => $result['total']],
                'تم جلب قائمة التحويلات بنجاح'
            );
        }

        //  Provider
        if (Auth::guard('provider')->check()) {
            $data = $this->service->getProviderTransactions();
            return $this->success($data, 'تم جلب قائمة التحويلات بنجاح');
        }

        //  Driver
        if (Auth::guard('driver')->check()) {
            $data = $this->service->getDriverTransactions();
            return $this->success($data, 'تم جلب قائمة التحويلات بنجاح');
        }

        return $this->error('غير مصرح لك بالوصول إلى هذه البيانات.', 403);
    }
    public function show($id)
    {
        $transaction = Transaction::find($id);
        if (!$transaction) {
            return $this->error('التحويل غير موجود', 404);
        }

        $data = $this->service->getTransactionDetails($transaction);

        if (!$data) {
            return $this->error('غير مصرح لك بعرض هذا التحويل', 403);
        }

        return $this->success($data, 'تم جلب بيانات التحويل بنجاح');
    }

    public function destroy(Transaction $transaction)
    {
        //  Admin only
        if (!Auth::user()?->is_admin) {
            return $this->error('غير مصرح لك بحذف هذا التحويل', 403);
        }

        $transaction->delete();

        return $this->success(null, 'تم حذف التحويل بنجاح');
    }

    public function deleteAllProviderTansactions(Provider $provider)
    {
        //  Admin only
        if (!Auth::user()?->is_admin) {
            return $this->error('غير مصرح لك بحذف هذا التحويل', 403);
        }
        Transaction::where('provider_id', $provider->id)->delete();

        return $this->success(null, 'تم حذف التحويلات بنجاح');
    }
    public function deleteAllTansactions()
    {
        // Admin only
        if (!Auth::user()?->is_admin) {
            return $this->error('غير مصرح لك بحذف التحويلات', 403);
        }
        
        Transaction::query()->delete();

        return $this->success(null, 'تم حذف جميع التحويلات بنجاح');
    }
}
