<?php

namespace App\Http\Controllers\UserManagementControllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagementRequests\LoginRequest;
use App\Services\UserManagementServices\UserManagementService;
use App\Http\Requests\UserManagementRequests\StoreUserFormRequest;

class UserManagementController extends Controller
{
    protected UserManagementService $service;

    public function __construct(UserManagementService $service)
    {
        $this->service = $service;
    }

    public function register(StoreUserFormRequest $request)
    {
        $result = $this->service->register($request->validated());
        return $this->success($result, "registered successfully", 201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->service->login($request->validated());

        if (!$result['success']) {
            return $this->error($result['message'], 401);
        }

        return $this->success($result['data'], "Login successfully");
    }

    public function logout(Request $request)
    {
        $result = $this->service->logout($request->user());
        return $this->success($result, "Logout success");
    }
}
