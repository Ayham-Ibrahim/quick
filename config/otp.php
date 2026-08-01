<?php

return [
    // Which provider is used to deliver OTP codes: 'sms' (msgPlus) or 'whatsapp' (HyperMsg).
    // WhatsApp is temporarily suspended in favor of SMS; switch back by setting OTP_CHANNEL=whatsapp.
    'channel' => env('OTP_CHANNEL', 'sms'),
];
