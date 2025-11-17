<?php

namespace App\Http\Controllers\UserManagementControllers;

use App\Http\Controllers\Controller;
use App\Services\UserManagementServices\UserManagementService;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    protected $UserManagementService;

    public function __construct(UserManagementService $UserManagementService)
    {
        $this->UserManagementService = $UserManagementService;
    }

    public function register() {}

    public function login() {}

    public function logout() {}
}
