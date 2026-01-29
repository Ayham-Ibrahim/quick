<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\Store;
use App\Models\UserManagement\Provider;
use App\Models\UserManagement\User;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Send Broadcast Notification Job
 * 
 * Handles sending notifications to multiple target types via FCM.
 * Processes in batches to avoid memory issues with large datasets.
 */
class SendBroadcastNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Notification $notification
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(FcmService $fcmService): void
    {
        try {
            $this->notification->markAsSending();

            $totalSent = 0;

            foreach ($this->notification->target_types as $targetType) {
                $sent = match ($targetType) {
                    Notification::TARGET_USERS => $this->sendToUsers($fcmService),
                    Notification::TARGET_PROVIDERS => $this->sendToProviders($fcmService),
                    Notification::TARGET_STORES => $this->sendToStores($fcmService),
                    Notification::TARGET_DRIVERS => $this->sendToDrivers($fcmService),
                    default => 0,
                };

                $totalSent += $sent;
            }

            $this->notification->markAsCompleted($totalSent);

            Log::info("Broadcast notification sent successfully", [
                'notification_id' => $this->notification->id,
                'total_sent' => $totalSent,
            ]);
        } catch (\Exception $e) {
            $this->notification->markAsFailed();
            
            Log::error("Failed to send broadcast notification", [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Send notification to all users.
     */
    private function sendToUsers(FcmService $fcmService): int
    {
        $tokens = Device::where('owner_type', User::class)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return $this->sendBatchNotifications($fcmService, $tokens);
    }

    /**
     * Send notification to all providers.
     */
    private function sendToProviders(FcmService $fcmService): int
    {
        $tokens = Device::where('owner_type', Provider::class)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return $this->sendBatchNotifications($fcmService, $tokens);
    }

    /**
     * Send notification to all stores.
     */
    private function sendToStores(FcmService $fcmService): int
    {
        $tokens = Device::where('owner_type', Store::class)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return $this->sendBatchNotifications($fcmService, $tokens);
    }

    /**
     * Send notification to all drivers.
     */
    private function sendToDrivers(FcmService $fcmService): int
    {
        $tokens = Device::where('owner_type', Driver::class)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return $this->sendBatchNotifications($fcmService, $tokens);
    }

    /**
     * Send notifications in batches.
     */
    private function sendBatchNotifications(FcmService $fcmService, array $tokens): int
    {
        if (empty($tokens)) {
            return 0;
        }

        // Process in chunks of 500 to avoid memory issues
        $chunks = array_chunk($tokens, 500);
        $totalSent = 0;

        foreach ($chunks as $chunk) {
            try {
                $result = $fcmService->sendToMultipleTokens(
                    $chunk,
                    $this->notification->title,
                    $this->notification->content,
                    [
                        'type' => 'broadcast',
                        'notification_id' => (string) $this->notification->id,
                    ]
                );

                $totalSent += $result['success'];

                // Update sent count incrementally
                $this->notification->incrementSentCount($result['success']);
            } catch (\Exception $e) {
                Log::error("Failed to send notification batch", [
                    'notification_id' => $this->notification->id,
                    'batch_size' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $totalSent;
    }
}
