<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Event Publishing Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of events a single API identity (authenticated user
    | publishing to a project) may publish within the decay window.
    |
    */

    'event_publishing' => [
        'max_attempts' => (int) env('RATE_LIMIT_EVENTS_PER_MINUTE', 60),
        'decay_seconds' => (int) env('RATE_LIMIT_EVENTS_DECAY_SECONDS', 60),
    ],

];
