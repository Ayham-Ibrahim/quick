<?php

namespace App\Http\Controllers\UserManagementControllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagementRequests\LoginRequest;
use App\Services\UserManagementServices\UserManagementService;
use App\Http\Requests\UserManagementRequests\StoreUserFormRequest;

class UserManagementController extends Controller
{
    protected $UserManagementService;

    public function __construct(UserManagementService $UserManagementService)
    {
        $this->UserManagementService = $UserManagementService;
    }

    public function register(StoreUserFormRequest $request)
    {
        $result = $this->UserManagementService->register($request->validated());
        return response()->json($result, 201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->UserManagementService->login($request->validated());

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 401);
        }

        return response()->json($result, 200);
    }

    public function logout(Request $request)
    {
        $result = $this->UserManagementService->logout($request);
        return response()->json($result, 200);
    }
}
