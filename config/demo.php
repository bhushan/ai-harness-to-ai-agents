<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Model
    |---------------------------------------------------------------------------
    |
    | The model id that goes out in every request payload. Nothing in this repo
    | ever reaches api.anthropic.com, but the payloads are the real shape, so
    | the model id is the real one too.
    |
    */

    'model' => env('DEMO_MODEL', 'claude-opus-5'),

    'max_tokens' => (int) env('DEMO_MAX_TOKENS', 1024),

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    |
    | Every model response and every gateway response is served from a committed
    | file. There is no network client anywhere in this application. A missing
    | fixture is a loud failure, never a silent fallback.
    |
    */

    'fixtures' => [
        'llm' => base_path('tests/fixtures/llm'),
        'stripe' => base_path('tests/fixtures/stripe'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Request recording
    |---------------------------------------------------------------------------
    |
    | Outgoing request payloads are written here so a real payload can be opened
    | on stage. Set to null to turn recording off.
    |
    */

    'record_requests_to' => storage_path('demo/requests'),

];
