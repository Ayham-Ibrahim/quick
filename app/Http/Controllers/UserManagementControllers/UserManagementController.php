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
use App\Http\Requests\UserManagementRequests\UpdateUserProfileRequest;
use Illuminate\Support\Facades\Auth;

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

  public function refreshToken(Request $request)
{
    $refreshToken = $request->bearerToken();
    if (!$refreshToken) {
        $refreshToken = $request->input('refresh_token');
    }

    $personal = \Laravel\Sanctum\PersonalAccessToken::findToken($refreshToken);

    if (!$personal) {
        return [
            'success' => false,
            'message' => 'Refresh token غير صالح'
        ];
    }

    $abilities = $personal->abilities;
    $user = $personal->tokenable;

    // ---------------------------
    //     ADMIN REFRESH SYSTEM
    // ---------------------------
    if (in_array('refresh-dashboard', $abilities)) {


        $newAccess = $user->createToken(
            'admin-access',
            ['dashboard'],
            now()->addMinutes(10)
        )->plainTextToken;


        return [
            'success' => true,
            'data' => [
                'access_token'  => $newAccess,
                'refresh_token' => $refreshToken,
                'expires_in'    => 600, // 10 min
                'type'          => 'admin'
            ]
        ];
    }

    // ---------------------------
    //     MOBILE REFRESH SYSTEM
    // ---------------------------
    if (in_array('refresh-token', $abilities)) {
            $personal->delete();
            $newAccess = $user->createToken(
                'mobile-access',
                ['access-api'],
                now()->addHours(2)
            )->plainTextToken;

            $newRefresh = $user->createToken(
                'mobile-refresh',
                ['refresh-token'],
                now()->addYear()
            )->plainTextToken;

            return [
                'success' => true,
                'data' => [
                    'access_token'  => $newAccess,
                    'refresh_token' => $newRefresh,
                    'expires_in'    => 7200, // 2 hours
                    'type'          => 'mobile'
                ]
            ];
        }

        return [
            'success' => false,
            'message' => 'هذا التوكن ليس Refresh Token'
        ];
    }

    /**
     * delete user account
     */
    public function deleteAccount(Request $request)
    {
        $authAccount = $request->user();

        $result = $this->service->deleteAccount($authAccount);

        if (! $result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success([], "Account deleted successfully");
    }


    /**
     * user profile
     */
    public function profile(){
        $user = Auth::user();
        return $this->success($user,'تم جلب بيانات المستخدم بنجاح');
    }

    /**
     * Update provider data.
     */
    public function updateProfile(UpdateUserProfileRequest $request)
    {
        $updated = $this->service->updateProfile( $request->validated());
        return $this->success($updated, 'تم تحديث بياناتك بنجاح');
    }
}
