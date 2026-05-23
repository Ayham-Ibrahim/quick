<?php

namespace Tests\Unit;

use App\Services\UserManagementServices\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    /** @test */
    public function it_sends_otp_to_v2_endpoint_with_digits_only_receiver(): void
    {
        config()->set('hypermsg.base_url', 'https://message.dashboard.technoplus.tech/whatsapp/api/v2');
        config()->set('hypermsg.send_text_path', 'message/text/send');
        config()->set('hypermsg.session_id', 'abc-123');
        config()->set('hypermsg.access_token', 'token-123');

        Http::fake([
            'https://message.dashboard.technoplus.tech/whatsapp/api/v2/message/text/send' => Http::response([
                'success' => true,
                'data' => [
                    'tracking_id' => 'msg_123',
                    'status' => 'sent',
                ],
                'message' => 'Message sent successfully.',
            ], 200),
        ]);

        $sent = app(WhatsAppService::class)->sendOTP('+963996597860', '1234', 'register');

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://message.dashboard.technoplus.tech/whatsapp/api/v2/message/text/send'
                && $request['session_id'] === 'abc-123'
                && $request['receiver'] === '963996597860'
                && str_contains($request['text'], '1234');
        });
    }
}