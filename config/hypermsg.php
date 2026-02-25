<?php

return [
    'base_url' => env('WHATSAPP_BASE_URL', 'https://message.dashboard.technoplus.tech/whatsapp/api/v1'),
    'send_text_path' => env('WHATSAPP_SEND_TEXT_PATH', 'message/text/send'),
    'session_id' => env('WHATSAPP_SESSION_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
];