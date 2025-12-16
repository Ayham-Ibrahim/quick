<?php

namespace App\Services\UserManagementServices;


use App\Models\OTPCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WhatsAppService
{
    protected $apiKey;
    protected $baseUrl;
    protected $whatsappNumberId;

    /**
     * Constructor to initialize API credentials.
     */
    public function __construct()
    {
        $this->apiKey = config('hypermsg.api_key');
        $this->baseUrl = config('hypermsg.base_url');
        $this->whatsappNumberId = config('hypermsg.whatsapp_number_id');
    }

    /**
     * Send OTP via WhatsApp.
     */
    public function sendOTP($phoneNumber, $otpCode, $type = 'register')
    {
        $message = $this->getMessageByType($type, $otpCode);

        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
            ])->post($this->baseUrl . '/whatsapp/messages/send', [
                'phone_number' => $phoneNumber,
                'message' => $message,
                'whatsapp_number_id' => $this->whatsappNumberId,
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