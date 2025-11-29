<?php
namespace App\Services\UserManagementServices;

use Carbon\Carbon;
use App\Models\Store;
use App\Services\FileStorage;
use Illuminate\Support\Facades\DB;
use App\Models\UserManagement\User;
use Illuminate\Support\Facades\Hash;
use App\Models\UserManagement\Provider;
use App\Services\UserManagementServices\OTPService;

class UserManagementService
{
    protected $otpService;

    public function __construct(OTPService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     *
     * @param array $data
     * @return array{token: string, user: User}
     */
    public function register(array $data)
    {
        DB::beginTransaction();

        try {
            // التحقق من وجود الرقم مسبقاً
            $existingUser = User::where('phone', $data['phone'])->first();

            if ($existingUser) {
                // إذا كان الحساب موجود ومفعل
                if ($existingUser->isPhoneVerified()) {
                    return [
                        'success' => false,
                        'message' => 'رقم الهاتف مسجل مسبقاً',
                    ];
                } 
            }

            // إنشاء حساب جديد غير مؤكد
            $avatarPath = isset($data['avatar'])
                ? FileStorage::storeFile($data['avatar'], 'avatars', 'img')
                : null;

            $user = User::create([
                'name'              => $data['name'],
                'phone'             => $data['phone'],
                'gender'            => $data['gender'],
                'city'              => $data['city'],
                'password'          => Hash::make($data['password']),
                'avatar'            => $avatarPath,
                'v_location'        => $data['v_location'],
                'h_location'        => $data['h_location'],
                'is_admin'          => 0,
                'phone_verified_at' => null, // غير مؤكد
            ]);

            // إرسال OTP للتأكيد
            try {
                $this->otpService->generateOTP($data['phone'], 'register');

                DB::commit();

                return [
                    'success'        => true,
                    'otp_required'   => true,
                    'account_exists' => false,
                    'message'        => 'تم إنشاء الحساب بنجاح. تم إرسال كود التحقق إلى واتساب',
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'فشل في إرسال كود التحقق',
                ];
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'فشل في إنشاء الحساب',
            ];
        }
    }

    /**
     * تأكيد الحساب مع OTP (عملية منفصلة)
     */
    public function confirmRegistration(array $data)
    {
        DB::beginTransaction();

        try {
            $user = User::where('phone', $data['phone'])->first();

            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'الحساب غير موجود',
                ];
            }

            if ($user->isPhoneVerified()) {
                return [
                    'success' => false,
                    'message' => 'الحساب مفعل مسبقاً',
                ];
            }

            // التحقق من OTP
            $verification = $this->otpService->verifyOTP(
                $data['phone'],
                $data['otp_code'],
                'register'
            );

            if (! $verification['success']) {
                return $verification;
            }

            // تفعيل الحساب
            $user->update([
                'phone_verified_at' => Carbon::now(),
            ]);
            $token = $user->createToken('mobile-token')->plainTextToken;

            DB::commit();

            // إعادة بيانات المستخدم بعد التأكيد
            return [
                'success' => true,
                'data'    => [
                    'user'    => $user->fresh(),
                    'token'   => $token,
                    'message' => 'تم تفعيل الحساب بنجاح',
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'فشل في تفعيل الحساب',
            ];
        }
    }

    public function login(array $credentials)
    {
        $model = match ($credentials['type']) {
            'user'          => User::class,
            'provider'      => Provider::class,
            'store_manager' => Store::class,
        };

        $account = $model::where('phone', $credentials['phone'])->first();

        if (! $account || ! Hash::check($credentials['password'], $account->password)) {
            return [
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة',
            ];
        }

        if ($credentials['type'] === 'user' && !$account->isPhoneVerified()) {
            try {
                $this->otpService->generateOTP($credentials['phone'], 'register');
                
                return [
                    'success' => true,
                    'otp_required' => true,
                    'phone_verified' => false,
                    'message' => 'الحساب غير مفعل. تم إرسال كود التحقق إلى واتساب'
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'فشل في إرسال كود التحقق'
                ];
            }
        }
        $token = $account->createToken('mobile-token')->plainTextToken;

        return [
            'success' => true,
            'data'    => [
                'type'  => $credentials['type'],
                'user'  => $account,
                'token' => $token,
            ],
        ];
    }

    public function confirmLogin(array $data)
    {
        DB::beginTransaction();

        try {
            $user = User::where('phone', $data['phone'])->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'الحساب غير موجود'
                ];
            }

            // التحقق من OTP
            $verification = $this->otpService->verifyOTP(
                $data['phone'],
                $data['otp_code'],
                'register'
            );

            if (!$verification['success']) {
                return $verification;
            }

            // تفعيل الحساب
            $user->update([
                'phone_verified_at' => Carbon::now()
            ]);

            // إنشاء token بعد التفعيل
            $token = $user->createToken('mobile-token')->plainTextToken;

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'type' => 'user',
                    'user' => $user->fresh(),
                    'token' => $token,
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'فشل في تأكيد الحساب'
            ];
        }
    }

    public function logout($user)
    {
        $user->currentAccessToken()->delete();
        return ['message' => 'Logged out'];
    }


    

    /**
     * نسيان كلمة المرور - إرسال OTP فقط
     */
    public function forgotPassword(array $data)
    {
        $model = $this->getModel($data['type']);
        $account = $model::where('phone', $data['phone'])->first();

        if (!$account) {
            return [
                'success' => false,
                'message' => 'رقم الهاتف غير مسجل'
            ];
        }

        try {
            $this->otpService->generateOTP($data['phone'], 'reset_password');
            return [
                'success' => true,
                'message' => 'تم إرسال كود التحقق إلى واتساب'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'فشل في إرسال كود التحقق'
            ];
        }
    }

    /**
     * تأكيد OTP لنسيان كلمة المرور (عملية منفصلة)
     */
    public function confirmForgotPassword(array $data)
    {
        $model = $this->getModel($data['type']);
        $account = $model::where('phone', $data['phone'])->first();

        if (!$account) {
            return [
                'success' => false,
                'message' => 'رقم الهاتف غير مسجل'
            ];
        }

        // التحقق من OTP فقط
        $verification = $this->otpService->verifyOTP(
            $data['phone'],
            $data['otp_code'],
            'reset_password'
        );

        if (!$verification['success']) {
            return $verification;
        }

        // نعيد نجاح العملية فقط - التطبيق يوجهه لصفحة تغيير كلمة المرور
        return [
            'success' => true,
            'message' => 'تم التحقق بنجاح، يمكنك الآن تغيير كلمة المرور'
        ];
    }

    /**
     * تغيير كلمة المرور بعد التأكيد
     */
    public function resetPassword(array $data)
    {
        DB::beginTransaction();

        try {
            $model = $this->getModel($data['type']);
            $account = $model::where('phone', $data['phone'])->first();

            if (!$account) {
                return [
                    'success' => false,
                    'message' => 'رقم الهاتف غير مسجل'
                ];
            }

            // تحديث كلمة المرور مباشرة (تم التحقق مسبقاً)
            $account->update([
                'password' => Hash::make($data['password'])
            ]);

            $account->refresh();

            $passwordUpdated = Hash::check($data['password'], $account->password);
        
            if (!$passwordUpdated) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'فشل في تحديث كلمة المرور'
                ];
            }


            DB::commit();

            \Log::info('Password reset successfully', [
                'phone' => $data['phone'],
                'type' => $data['type'],
                'account_id' => $account->id
            ]);

            return [
                'success' => true,
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Password reset failed', [
            'phone' => $data['phone'],
            'type' => $data['type'],
            'error' => $e->getMessage()
        ]);
            return [
                'success' => false,
                'message' => 'فشل في إعادة تعيين كلمة المرور'
            ];
        }
    }

    /**
     * إعادة إرسال OTP
     */
    public function resendOTP($phone, $type)
    {
        try {
            $this->otpService->generateOTP($phone, $type);
            return [
                'success' => true,
                'message' => 'تم إعادة إرسال كود التحقق'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'فشل في إرسال كود التحقق'
            ];
        }
    }

    /**
     * الحصول على الـ Model المناسب
     */
    private function getModel($type)
    {
        return match ($type) {
            'user' => User::class,
            'provider' => Provider::class,
            'store_manager' => Store::class,
            default => User::class,
        };
    }


}
