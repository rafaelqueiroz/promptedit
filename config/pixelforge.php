<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pixelforge
    |--------------------------------------------------------------------------
    |
    | Credentials and limits for the Pixelforge image-generation API. Create
    | one API key per environment: each key has its own webhook URL and
    | signing secret, and GET /v1/usage reports each key separately.
    |
    */

    'base_url' => env('PIXELFORGE_BASE_URL', 'https://api.pixelforge.dev'),

    'api_key' => env('PIXELFORGE_API_KEY'),

    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),

    // [confirm] against the Pixelforge docs.
    'max_reference_images' => 4,

];
