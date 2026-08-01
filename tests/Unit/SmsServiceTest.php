<?php

namespace Tests\Unit;

use App\Services\UserManagementServices\SmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    /** @test */
    public function it_sends_otp_to_the_send_endpoint_with_digits_only_receiver(): void
    {
        config()->set('msgplus.base_url', 'https://sms.msgplus.tech/api/v1');
        config()->set('msgplus.api_key', 'api-key-123');
        config()->set('msgplus.sender_id', 1);
        config()->set('msgplus.template_id', 1);
        config()->set('msgplus.otp_name_var', 'P1');
        config()->set('msgplus.otp_name_value', '');
        config()->set('msgplus.otp_code_var', 'P2');

        Http::fake([
            'https://sms.msgplus.tech/api/v1/send' => Http::response([
                'success' => true,
                'sms_log_id' => 42,
                'queued' => true,
            ], 202),
        ]);

        $sent = app(SmsService::class)->sendOTP('+963996597860', '1234', 'register');

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.msgplus.tech/api/v1/send'
                && $request->hasHeader('X-Timestamp')
                && $request['sender_id'] === 1
                && $request['template_id'] === 1
                && $request['numbers'] === ['963996597860']
                && $request['vars']['P2'] === '1234';
        });
    }
}
