<?php

namespace App\Services\UserManagementServices;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SmsService
{
    protected $baseUrl;
    protected $apiKey;
    protected $senderId;
    protected $templateId;
    protected $otpNameVar;
    protected $otpNameValue;
    protected $otpCodeVar;

    /**
     * Constructor to initialize API credentials.
     */
    public function __construct()
    {
        $this->baseUrl = config('msgplus.base_url');
        $this->apiKey = config('msgplus.api_key');
        $this->senderId = config('msgplus.sender_id');
        $this->templateId = config('msgplus.template_id');
        $this->otpNameVar = config('msgplus.otp_name_var', 'P1');
        $this->otpNameValue = config('msgplus.otp_name_value', '');
        $this->otpCodeVar = config('msgplus.otp_code_var', 'P2');
    }

    /**
     * Send OTP via SMS (msgPlus).
     */
    public function sendOTP($phoneNumber, $otpCode, $type = 'register')
    {
        $endpoint = rtrim((string) $this->baseUrl, '/') . '/send';
        $receiver = $this->normalizeReceiver((string) $phoneNumber);

        if (empty($this->apiKey)) {
            Log::error('SMS OTP config missing api_key');
            return false;
        }

        if (empty($this->senderId) || empty($this->templateId)) {
            Log::error('SMS OTP config missing sender_id/template_id');
            return false;
        }

        if (empty($receiver)) {
            Log::error('SMS OTP receiver is empty', ['phone' => $phoneNumber]);
            return false;
        }

        try {
            $payload = [
                'sender_id' => (int) $this->senderId,
                'template_id' => (int) $this->templateId,
                'numbers' => [$receiver],
                'vars' => [
                    $this->otpNameVar => $this->otpNameValue,
                    $this->otpCodeVar => $otpCode,
                ],
            ];

            Log::debug('SMS OTP request', [
                'endpoint' => $endpoint,
                'payload' => $payload,
            ]);

            // msgPlus requires an X-Timestamp header (unix seconds) on /send.
            $response = Http::timeout(30)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->withHeaders(['X-Timestamp' => (string) time()])
                ->asJson()
                ->post($endpoint, $payload);

            if ($response->successful() && data_get($response->json(), 'success') === true) {
                Log::info('SMS OTP sent successfully', [
                    'phone' => $phoneNumber,
                    'type' => $type,
                    'status' => $response->status(),
                    'sms_log_id' => data_get($response->json(), 'sms_log_id'),
                ]);
                return true;
            }

            Log::error('Failed to send SMS OTP', [
                'phone' => $phoneNumber,
                'type' => $type,
                'endpoint' => $endpoint,
                'receiver' => $receiver,
                'status' => $response->status(),
                'response' => mb_substr($response->body(), 0, 500),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Exception while sending SMS OTP', [
                'phone' => $phoneNumber,
                'type' => $type,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function normalizeReceiver(string $phone): ?string
    {
        $clean = preg_replace('/[^0-9+]/', '', trim($phone));

        if ($clean === '' || $clean === null) {
            return null;
        }

        if (str_starts_with($clean, '00')) {
            $clean = substr($clean, 2);
        }

        if (str_starts_with($clean, '+')) {
            $clean = substr($clean, 1);
        }

        if (str_starts_with($clean, '09')) {
            $clean = '963' . substr($clean, 1);
        }

        if (str_starts_with($clean, '0')) {
            return null;
        }

        if (!preg_match('/^[0-9]{7,15}$/', $clean)) {
            return null;
        }

        return $clean;
    }
}
