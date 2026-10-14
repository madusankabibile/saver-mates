<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** HTTP client for calls to outside services (wallpapers, song details). */
class OutboundHttp
{
    public static function client(int $timeout): PendingRequest
    {
        $request = Http::connectTimeout(3)->timeout($timeout)->accept('*/*');

        // On Windows, verify certificates against the system store. PHP's own
        // CA bundle misses roots Windows trusts (e.g. antivirus HTTPS scanning),
        // which makes every HTTPS call fail. Verification itself stays on.
        // 16 = CURLSSLOPT_NATIVE_CA, which PHP only defines from 8.2.
        if (PHP_OS_FAMILY === 'Windows') {
            $request->withOptions(['curl' => [CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 16]]);
        }

        return $request;
    }
}
