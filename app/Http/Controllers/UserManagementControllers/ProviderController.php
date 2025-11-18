<?php

namespace App\Http\Controllers\UserManagementControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagementRequests\StoreProviderRequest;
use App\Models\UserManagement\Provider;
use App\Services\UserManagementServices\ProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProviderController extends Controller
{

    protected ProviderService $providerService;

    public function __construct(ProviderService $providerService)
    {
        $this->providerService = $providerService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    public function store(StoreProviderRequest $request)
    {
        $provider = $this->providerService->createProvider($request->validated());

        return $this->success($provider, 'تم إنشاء حساب المزود بنجاح', 201);
    }
    /**
     * Display the specified resource.
     */
    public function show(Provider $provider)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Provider $provider)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Provider $provider)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Provider $provider)
    {
        //
    }
}
