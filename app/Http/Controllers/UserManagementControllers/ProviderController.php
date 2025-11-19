<?php

namespace App\Http\Controllers\UserManagementControllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\UserManagement\Provider;
use App\Services\UserManagementServices\ProviderService;
use App\Http\Requests\UserManagementRequests\provider\StoreProviderRequest;
use App\Http\Requests\UserManagementRequests\provider\UpdateProviderRequest;

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
        $providers = Provider::latest()->paginate(10);
        return $this->paginate($providers, 'تم جلب قائمة المزودين بنجاح');
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
     * Remove provider.
     */
    public function destroy(Provider $provider)
    {
        $this->providerService->deleteProvider($provider);
        return $this->success(null, 'تم حذف حساب المزود بنجاح');
    }
}
