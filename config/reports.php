<?php

return [

    // How long, on average, it took to confirm a reservation BEFORE this system, in minutes
    // (for example, the owner's usual reply time over chat). Leave empty if unknown.
    // 180 = 3 hours, 1440 = 1 day.
    'baseline_confirm_minutes' => env('REPORT_BASELINE_CONFIRM_MINUTES') !== null && env('REPORT_BASELINE_CONFIRM_MINUTES') !== ''
        ? (float) env('REPORT_BASELINE_CONFIRM_MINUTES')
        : null,

    // Targets from the study's specific objectives
    'target_time_reduction_percent' => 60,   // objective: time to a confirmed reservation down by 60%
    'target_completion_percent' => 80,       // objective: at least 80% of online requests end in a confirmed reservation

    // Busiest gowns shown on the page
    'top_gowns' => 5,

];
