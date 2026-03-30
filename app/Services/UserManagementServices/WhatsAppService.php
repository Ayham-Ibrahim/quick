<?php

namespace App\Services\UserManagementServices;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected $baseUrl;
    protected $sendTextPath;
    protected $sessionId;
    protected $accessToken;

    /**
     * Constructor to initialize API credentials.
     */
    public function __construct()
    {
        $this->baseUrl = config('hypermsg.base_url');
        $this->sendTextPath = config('hypermsg.send_text_path', 'message/text/send');
        $this->sessionId = config('hypermsg.session_id');
        $this->accessToken = config('hypermsg.access_token');
    }

    /**
     * Send OTP via WhatsApp.
     */
    public function sendOTP($phoneNumber, $otpCode, $type = 'register')
    {
        $message = $this->getMessageByType($type, $otpCode);
        $endpoint = rtrim((string) $this->baseUrl, '/') . '/' . ltrim((string) $this->sendTextPath, '/');
        $sessionId = trim((string) $this->sessionId, " \t\n\r\0\x0B\"'");
        $receiver = $this->normalizeReceiver((string) $phoneNumber);

        if (empty($sessionId)) {
            Log::error('WhatsApp OTP config missing session_id');
            return false;
        }

        if (empty($receiver)) {
            Log::error('WhatsApp OTP receiver is empty', ['phone' => $phoneNumber]);
            return false;
        }

        try {
            $payload = [
                'session_id' => $sessionId,
                'receiver' => $receiver,
                'text' => $message,
            ];

            Log::debug('WhatsApp OTP request', [
                'endpoint' => $endpoint,
                'payload' => $payload,
            ]);

            // Send with Bearer token authentication (like Postman)
            // withoutVerifying() bypasses SSL cert check (for local dev without CA certs)
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->withToken($this->accessToken)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            if ($response->successful()) {
                Log::info('WhatsApp OTP sent successfully', [
                    'phone' => $phoneNumber,
                    'type' => $type,
                    'status' => $response->status(),
                    'response' => $response->json() ?? $response->body(),
                ]);
                return true;
            }

            Log::error('Failed to send WhatsApp OTP', [
                'phone' => $phoneNumber,
                'type' => $type,
                'endpoint' => $endpoint,
                'receiver' => $receiver,
                'session_id_length' => strlen($sessionId),
                'status' => $response->status(),
                'response' => mb_substr($response->body(), 0, 500),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp OTP', [
                'phone' => $phoneNumber,
                'type' => $type,
                'endpoint' => $endpoint,
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

   private function normalizeReceiver(string $phone): ?string
{
    $clean = preg_replace('/[^0-9+]/', '', trim($phone));

    if ($clean === '' || $clean === null) {
        return null;
    }

    // + or 00 international prefix
    if (str_starts_with($clean, '00')) {
        $clean = substr($clean, 2);
    }

    if (str_starts_with($clean, '+')) {
        $clean = substr($clean, 1);
    }

    // Syrian local format: 09xxxxxxx -> 9639xxxxxxx
    if (str_starts_with($clean, '09')) {
        $clean = '963' . substr($clean, 1);
    }

    // رفض الأرقام المحلية غير المحولة (لا يوجد فرض كود دولة عام)
    if (str_starts_with($clean, '0')) {
        return null;
    }

    if (!preg_match('/^[0-9]+$/', $clean)) {
        return null;
    }

    return '+' . $clean;
}
}