<?php

namespace App\Http\Controllers\UserManagementControllers;

use App\Http\Requests\UserManagementRequests\provider\UpdateProviderProfileRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\UserManagement\Provider;
use App\Services\UserManagementServices\ProviderService;
use App\Http\Requests\UserManagementRequests\provider\StoreProviderRequest;
use App\Http\Requests\UserManagementRequests\provider\UpdateProviderRequest;
use Illuminate\Support\Facades\Auth;

class ProviderController extends Controller
{
    protected ProviderService $providerService;

    public function __construct(ProviderService $providerService)
    {
        $this->providerService = $providerService;
    }

    /**
     * Display a listing of providers.
     */
    public function index()
    {
        if(Auth::user()->is_admin){
            $providers = Provider::paginate(10);
            return $this->paginate($providers, 'تم جلب قائمة المزودين بنجاح');
        }else{
            $providers = Provider::latest()->select(['id', 'provider_name', 'market_name'])->get();
            return $this->success($providers, 'تم جلب قائمة المزودين بنجاح');
        }
       }

    /**
     * Store a newly created provider.
     */
    public function store(StoreProviderRequest $request)
    {
        $provider = $this->providerService->createProvider($request->validated());
        return $this->success($provider, 'تم إنشاء حساب المزود بنجاح', 201);
    }

    /**
     * Display a specific provider.
     */
    public function show(Provider $provider)
    {
        return $this->success($provider, 'تم جلب بيانات المزود بنجاح');
    }

    /**
     * Update provider data.
     */
    public function update(UpdateProviderRequest $request, Provider $provider)
    {
        $updated = $this->providerService->updateProvider($provider, $request->validated());
        return $this->success($updated, 'تم تحديث بيانات المزود بنجاح');
    }
    /**
     * Update provider data.
     */
    public function updateProviderProfile(UpdateProviderProfileRequest $request)
    {
        $updated = $this->providerService->updateProviderProfile( $request->validated());
        return $this->success($updated, 'تم تحديث بياناتك بنجاح');
    }

    public function profile()
    {
        return $this->success(Auth::user(), 'بيانات المزود');
    }

    /**
     * Remove provider.
     */
    public function destroy(Provider $provider)
    {
        $this->providerService->deleteProvider($provider);
        return $this->success(null, 'تم حذف حساب المزود بنجاح');
    }
}
