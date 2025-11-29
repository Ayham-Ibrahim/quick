<?php

namespace App\Http\Controllers\UserManagementControllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagementRequests\LoginRequest;
use App\Http\Requests\UserManagementRequests\ResendOTPRequest;
use App\Services\UserManagementServices\UserManagementService;
use App\Http\Requests\UserManagementRequests\ConfirmLoginRequest;
use App\Http\Requests\UserManagementRequests\ResetPasswordRequest;
use App\Http\Requests\UserManagementRequests\StoreUserFormRequest;
use App\Http\Requests\UserManagementRequests\ForgotPasswordRequest;
use App\Http\Requests\UserManagementRequests\ConfirmRegistrationRequest;
use App\Http\Requests\UserManagementRequests\ConfirmForgotPasswordRequest;

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
        
        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }
        if (isset($result['otp_required']) && $result['otp_required']) {
            return $this->success([
                'otp_required' => true,
                'account_exists' => $result['account_exists'] ?? false,
                'message' => $result['message']
            ]);
        }

        return $this->success($result, "registered successfully", 201);
    }

        /**
     * تأكيد التسجيل مع OTP
     */
    public function confirmRegistration(ConfirmRegistrationRequest $request)
    {
        $result = $this->service->confirmRegistration($request->validated());

        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success($result['data'], "Registration confirmed successfully");
    }


    public function login(LoginRequest $request)
    {
        $result = $this->service->login($request->validated());

        if (!$result['success']) {
            return $this->error($result['message'], 401);
        }
        if (isset($result['otp_required']) && $result['otp_required']) {
            return $this->success([
                'otp_required' => true,
                'phone_verified' => false,
                'message' => $result['message']
            ]);
        }

        return $this->success($result['data'], "Login successfully");
    }

     /**
     * تأكيد الحساب أثناء Login
     */
    public function confirmLogin(ConfirmLoginRequest $request)
    {
        $result = $this->service->confirmLogin($request->validated());

        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success($result['data'], "Login confirmed successfully");
    }

    public function logout(Request $request)
    {
        $result = $this->service->logout($request->user());
        return $this->success($result, "Logout success");
    }

    /**
     * نسيان كلمة المرور - إرسال OTP فقط
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $result = $this->service->forgotPassword($request->validated());

        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * تأكيد OTP لنسيان كلمة المرور
     */
    public function confirmForgotPassword(ConfirmForgotPasswordRequest $request)
    {
        $result = $this->service->confirmForgotPassword($request->validated());

        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * تغيير كلمة المرور بعد التأكيد
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $result = $this->service->resetPassword($request->validated());

        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * إعادة إرسال OTP
     */
    public function resendOTP(ResendOTPRequest $request)
    {
        $result = $this->service->resendOTP(
            $request->phone,
            $request->type
        );

        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], $result['message']);
    }

    
}
