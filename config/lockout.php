<?php
return [
    'max_attempts' => (int) env('APP_LOCKOUT_MAX_ATTEMPTS', 5),
    'duration'     => (int) env('APP_LOCKOUT_DURATION', 300),
];
