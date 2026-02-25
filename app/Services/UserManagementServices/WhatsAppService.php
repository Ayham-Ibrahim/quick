<?php

namespace App\Services\UserManagementServices;


use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected $baseUrl;
    protected $sendTextPath;
    protected $sessionId;

    /**
     * Constructor to initialize API credentials.
     */
    public function __construct()
    {
        $this->baseUrl = config('hypermsg.base_url');
        $this->sendTextPath = config('hypermsg.send_text_path', 'message/text/send');
        $this->sessionId = config('hypermsg.session_id');
    }

    /**
     * Send OTP via WhatsApp.
     */
    public function sendOTP($phoneNumber, $otpCode, $type = 'register')
    {
        $message = $this->getMessageByType($type, $otpCode);
        $endpoint = rtrim((string) $this->baseUrl, '/') . '/' . ltrim((string) $this->sendTextPath, '/');

        if (empty($this->sessionId)) {
            Log::error('WhatsApp OTP config missing session_id');
            return false;
        }

        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'session_id' => $this->sessionId,
                'receiver' => $phoneNumber,
                'text' => $message,
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp OTP sent successfully', [
                    'phone' => $phoneNumber,
                    'type' => $type,
                    'response' => $response->json()
                ]);
                return true;
            } else {
                Log::error('Failed to send WhatsApp OTP', [
                    'phone' => $phoneNumber,
                    'type' => $type,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp OTP', [
                'phone' => $phoneNumber,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function getMessageByType($type, $otpCode)
    {
        $messages = [
            'register' => "كود التحقق لإنشاء الحساب: {$otpCode}\n\nهذا الكود صالح لمدة 4 دقائق",
            'reset_password' => "كود التحقق لإعادة تعيين كلمة المرور: {$otpCode}\n\nهذا الكود صالح لمدة 4 دقائق",
        ];

        return $messages[$type] ?? "كود التحقق الخاص بك هو: {$otpCode}\n\nهذا الكود صالح لمدة 4 دقائق";
    }
}