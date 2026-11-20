<?php

return [

    // Send emails to customers. Which mail service is used is set by MAIL_* in .env
    // (MAIL_MAILER=log only writes emails to storage/logs/laravel.log, which is fine for testing).
    'email_enabled' => env('NOTIFY_EMAIL', true),

    // log        = do not send, only write the text message to storage/logs/laravel.log (testing)
    // semaphore  = send real text messages through semaphore.co (Philippines)
    // none       = no text messages at all
    'sms_driver' => env('SMS_DRIVER', 'log'),

    'semaphore' => [
        'key' => env('SEMAPHORE_API_KEY'),
        // Optional. Must be approved by Semaphore first. Leave empty to use their default.
        'sender' => env('SEMAPHORE_SENDER_NAME'),
    ],

    // The shop's own details. They appear at the bottom of customer emails,
    // and SHOP_EMAIL gets an alert when a customer books or cancels online.
    'shop' => [
        'email' => env('SHOP_EMAIL'),
        'phone' => env('SHOP_PHONE'),
        'address' => env('SHOP_ADDRESS'),
    ],

    // Time of day (shop time) that the "tomorrow is your pick-up / return day" reminders go out
    'reminder_time' => env('REMINDER_TIME', '09:00'),

];
