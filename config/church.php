<?php

return [
    'name'          => env('CHURCH_NAME', null),
    'tagline'       => env('CHURCH_TAGLINE', null),
    'logo'          => env('CHURCH_LOGO', null),
    'primary_color' => env('CHURCH_PRIMARY_COLOR', '#6366f1'),
    'address'       => env('CHURCH_ADDRESS', null),
    'phone'         => env('CHURCH_PHONE', null),
    'email'         => env('CHURCH_EMAIL', null),
    'socials' => [
        'facebook'  => env('CHURCH_FACEBOOK'),
        'instagram' => env('CHURCH_INSTAGRAM'),
        'youtube'   => env('CHURCH_YOUTUBE'),
        'twitter'   => env('CHURCH_TWITTER'),
    ],
    'service_times' => [],

    /*
    |--------------------------------------------------------------------------
    | Homepage Stats
    |--------------------------------------------------------------------------
    | Leave empty — each church configures their own stats via
    | Settings → Homepage. An empty array hides the stats banner entirely.
    */
    'stats' => [],
];
