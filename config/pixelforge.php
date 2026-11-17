<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pixelforge API constraints
    |--------------------------------------------------------------------------
    |
    | Fixed facts about the Pixelforge API that validation rules and the UI
    | read. These are not configuration: they mirror the published API and do
    | not vary by environment, so they have no env vars.
    |
    | Credentials live in config/services.php under 'pixelforge'.
    |
    */

    'max_reference_images' => 4,

    'max_prompt_characters' => 4000,

    // Pixelforge rejects webhook timestamps older than this.
    'webhook_tolerance_seconds' => 300,

    /*
     * How long a generation must have been in flight before the reconciler
     * polls for it. Short, because without a webhook endpoint polling is the
     * only thing that settles it.
     */
    'reconcile_after_seconds' => (int) env('PIXELFORGE_RECONCILE_AFTER', 15),

];
