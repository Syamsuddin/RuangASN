<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Live Master Switch
    |--------------------------------------------------------------------------
    | When FALSE (the default — dev/test/CI), every integration client uses its
    | deterministic STUB path and makes NO outbound network call, even if real
    | credentials happen to be configured. Flip to true (INTEGRATIONS_LIVE=true)
    | ONLY in an environment that is wired to the real upstream APIs.
    |
    | This is the single guard that keeps tests offline and deterministic.
    */
    'live' => env('INTEGRATIONS_LIVE', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP Defaults
    |--------------------------------------------------------------------------
    | Conservative timeouts applied to any real upstream call. Stubs ignore
    | these entirely.
    */
    'timeout'      => (int) env('INTEGRATIONS_TIMEOUT', 15),
    'retry_times'  => (int) env('INTEGRATIONS_RETRY', 2),

    /*
    |--------------------------------------------------------------------------
    | Provider Defaults
    |--------------------------------------------------------------------------
    | Per-provider scheduling + behavioural defaults. Credentials themselves
    | live in IntegrationSettingsService (DB-encrypted, env fallback) — never
    | here. `scheduled` toggles whether the daily console sync touches a
    | provider at all.
    */
    'providers' => [
        'siasn' => [
            'scheduled'      => true,
            'sync_operation' => 'sync_asn',
        ],
        'srikandi' => [
            'scheduled'      => true,
            'sync_operation' => 'sync_surat',
        ],
        'sipd' => [
            'scheduled'      => true,
            'sync_operation' => 'sync_program',
        ],
        'google_calendar' => [
            'scheduled'      => false, // per-user opt-in only
            'sync_operation' => 'sync_calendar',
        ],
        'whatsapp' => [
            'scheduled'      => false, // outbound message channel, not a sync
            'sync_operation' => 'send_message',
        ],
        'sso' => [
            'scheduled'      => false,
            'sync_operation' => 'sync_identity',
        ],
    ],
];
