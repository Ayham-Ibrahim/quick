<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Driver;
use App\Models\Store;
use App\Models\Notification;
use App\Models\UserManagement\User;
use App\Models\UserManagement\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Google\Auth\Credentials\ServiceAccountCredentials;

/**
 * FCM Service
 * 
 * Handles Firebase Cloud Messaging for push notifications.
 * Supports sending to single or multiple devices.
 */
class FcmService
{
    protected $firebaseProjectId;
    protected $credentialsPath;

    /**
     * Initialize Firebase configuration.
     */
    protected function initConfig()
    {
        $this->firebaseProjectId = config('services.firebase.project_id');
        $this->credentialsPath = storage_path('app/quick-9b4d4-firebase-adminsdk-fbsvc-2b7bc9ed65.json');
    }

    /**
     * Send notification to a single token.
     *
     * @param string $token FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return bool
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $this->initConfig();

        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/firebase.messaging',
            $this->credentialsPath
        );

        $authToken = $credentials->fetchAuthToken()['access_token'];
        $url = "https://fcm.googleapis.com/v1/projects/{$this->firebaseProjectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $token,
                'data' => array_map('strval', array_merge($data, [
                    'title' => $title,
                    'body' => $body,
                    'click_action' => "FLUTTER_NOTIFICATION_CLICK"
                ])),
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => "high",
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'content-available' => 1,
                            'badge' => 5,
                            'priority' => "high",
                        ]
                    ]
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $authToken,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        Log::debug('FCM Response', ['body' => $response->body(), 'status' => $response->status()]);

        return $response->successful();
    }

    /**
     * Send notification to a User (all their devices).
     *
     * @param User $user
     * @param string $title
     * @param string $body
     * @param array $data
     * @return int Number of successful sends
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        $tokens = Device::getTokens($user);
        $successCount = 0;

        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $successCount++;
            }
        }

        Log::info("FCM sent to User #{$user->id}", [
            'total_devices' => $tokens->count(),
            'successful' => $successCount
        ]);

        return $successCount;
    }

    /**
     * Send notification to a Driver (single device).
     *
     * @param Driver $driver
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToDriver(Driver $driver, string $title, string $body, array $data = []): bool
    {
        $token = $driver->getFcmToken();

        if (!$token) {
            Log::warning("Driver #{$driver->id} has no FCM token");
            return false;
        }

        return $this->sendToToken($token, $title, $body, $data);
    }

    /**
     * Send notification to a Provider (single device).
     *
     * @param Provider $provider
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToProvider(Provider $provider, string $title, string $body, array $data = []): bool
    {
        $token = $provider->getFcmToken();

        if (!$token) {
            Log::warning("Provider #{$provider->id} has no FCM token");
            return false;
        }

        return $this->sendToToken($token, $title, $body, $data);
    }

    /**
     * Send notification to a Store (single device).
     *
     * @param Store $store
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToStore(Store $store, string $title, string $body, array $data = []): bool
    {
        $token = $store->getFcmToken();

        if (!$token) {
            Log::warning("Store #{$store->id} has no FCM token");
            return false;
        }

        return $this->sendToToken($token, $title, $body, $data);
    }

    /**
     * Send notification to multiple drivers.
     *
     * @param \Illuminate\Support\Collection $drivers
     * @param string $title
     * @param string $body
     * @param array $data
     * @return int Number of successful sends
     */
    public function sendToDrivers($drivers, string $title, string $body, array $data = []): int
    {
        $successCount = 0;

        foreach ($drivers as $driver) {
            if ($this->sendToDriver($driver, $title, $body, $data)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * Send notification to multiple tokens (for broadcast notifications).
     *
     * @param array $tokens Array of FCM tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array ['success' => int, 'failed' => int]
     */
    public function sendToMultipleTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $successCount = 0;
        $failedCount = 0;

        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        Log::info("FCM batch send completed", [
            'total' => count($tokens),
            'success' => $successCount,
            'failed' => $failedCount,
        ]);

        return [
            'success' => $successCount,
            'failed' => $failedCount,
        ];
    }

    /**
     * Legacy method - Send notification using User model.
     * Kept for backward compatibility.
     *
     * @deprecated Use sendToUser(), sendToDriver(), etc. instead
     * @param User $user
     * @param string $title
     * @param string $body
     * @param mixed $tokens
     * @param array $data
     * @return bool
     */
    public function sendNotification(User $user, string $title, string $body, $tokens, array $data = [])
    {
        if (is_array($tokens)) {
            $successCount = 0;
            foreach ($tokens as $token) {
                if ($this->sendToToken($token, $title, $body, $data)) {
                    $successCount++;
                }
            }
            return $successCount > 0;
        }

        return $this->sendToToken($tokens, $title, $body, $data);
    }
}
