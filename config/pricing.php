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
    | Users pay Pixelforge's cost plus the markup, rounded up.
    |
    */

    'markup' => '1.25',

    /*
    | USD per 1,000,000 tokens, by token type, mirroring GET /v1/pricing.
    | Verified against the live API on 2026-11-16.
    */
    'rates' => [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ],

    'rate_unit_tokens' => 1_000_000,

    /*
    | Token counts used for the up-front estimate.
    |
    | Input text and output tokens are predictable at submission; reasoning
    | tokens are only known once a generation finishes, so the estimate uses
    | the midpoint of the documented range and the actual cost is always
    | recomputed from the usage Pixelforge reports.
    */
    'estimate_tokens' => [
        'characters_per_text_token' => 4,
        'per_reference_image' => 765,

        'output' => [
            'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
            'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
            'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
        ],

        'reasoning' => [
            'low' => ['min' => 0, 'max' => 500],
            'medium' => ['min' => 200, 'max' => 2000],
            'high' => ['min' => 1000, 'max' => 8000],
        ],
    ],

];
