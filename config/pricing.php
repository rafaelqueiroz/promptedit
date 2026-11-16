<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | Every amount is a decimal string in USD and is only ever handled by
    | App\Pricing\Money (bcmath). Never put a float in here.
    |
    | Users pay Pixelforge's cost plus the markup, rounded up to 8 decimals.
    |
    */

    'markup' => '1.25',

    /*
    | USD per token, by token type.
    |
    | [confirm] PLACEHOLDER VALUES. Replace with Pixelforge's published rate
    | table before anything is charged for real. The admin report compares our
    | numbers with GET /v1/usage, so these must match the docs exactly.
    */
    'rates' => [
        'prompt' => '0.000005',
        'reference_image' => '0.00001',
        'output' => '0.00004',
    ],

    /*
    | Token counts used for the up-front estimate. Output tokens are only known
    | once a generation finishes, so the estimate uses typical values; the
    | actual cost is always computed from the usage Pixelforge reports.
    |
    | [confirm] PLACEHOLDER VALUES, keyed by App\Enums\ImageSize and
    | App\Enums\ImageQuality.
    */
    'estimate_tokens' => [
        'prompt' => 32,
        'reference_image' => 765,
        'output' => [
            '1024x1024' => ['standard' => 1056, 'high' => 4160],
            '1024x1536' => ['standard' => 1584, 'high' => 6240],
            '1536x1024' => ['standard' => 1584, 'high' => 6240],
        ],
    ],

];
