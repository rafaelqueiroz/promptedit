<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media storage
    |--------------------------------------------------------------------------
    |
    | Where generated images are kept once downloaded from Pixelforge.
    |
    | This must be a publicly readable disk: the browser loads these images
    | directly. It deliberately does NOT fall back to FILESYSTEM_DISK, whose
    | default ('local') is private and would serve 403s. Deployments set
    | MEDIA_DISK=s3 so files survive a redeploy.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

];
