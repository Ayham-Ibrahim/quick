<?php

return [
    'base_url' => env('SMS_BASE_URL', 'https://sms.msgplus.tech/api/v1'),
    'api_key' => env('SMS_API_KEY'),
    'sender_id' => env('SMS_SENDER_ID'),
    'template_id' => env('SMS_TEMPLATE_ID'),

    // Names of the template variables (as configured on the msgPlus dashboard)
    // that receive the greeting/name and the OTP code. Empty otp_name_var means
    // the template takes no name var (e.g. the current OTP template only has P1).
    'otp_name_var' => env('SMS_OTP_NAME_VAR', ''),
    'otp_name_value' => env('SMS_OTP_NAME_VALUE', ''),
    'otp_code_var' => env('SMS_OTP_CODE_VAR', 'P1'),
];
