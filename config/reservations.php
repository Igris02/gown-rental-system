<?php

return [

    // Reservation dates follow the shop's local time, whatever the server clock is set to
    'timezone' => env('RESERVATION_TIMEZONE', 'Asia/Manila'),

    // Earliest pick-up. 1 means tomorrow, so customers cannot reserve for today.
    'min_lead_days' => 1,

    // Latest pick-up, counted from today
    'max_advance_days' => 180,

];
