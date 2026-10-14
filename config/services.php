<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /* Random profile banners for new members. The endpoint must answer with
       JSON whose "data" field is an image URL. Set it empty to turn it off. */
    /* Song details for uploads (title, artist, album, genre, cover), looked
       up by file name in a Deezer-compatible API. Empty turns it off. */
    'song_lookup' => [
        'url' => env('SONG_LOOKUP_URL', 'https://api.deezer.com'),
        // Tried when the first catalogue has nothing suitable (iTunes Search API).
        'fallback_url' => env('SONG_LOOKUP_FALLBACK_URL', 'https://itunes.apple.com'),
    ],

    'wallpaper' => [
        'url' => env('COVER_WALLPAPER_URL', 'https://v2.xxapi.cn/api/wallpaper'),
    ],

];
