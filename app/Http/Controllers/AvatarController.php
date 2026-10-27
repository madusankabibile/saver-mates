<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

class AvatarController extends Controller
{
    /**
     * Gradient initials avatar used whenever a member has not uploaded a photo.
     */
    public function initials(User $user): Response
    {
        [$from, $to] = $user->colorPair();
        $initials = e($user->initials());
        $id = 'g'.$user->id;

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128" role="img" aria-label="{$initials}">
          <defs>
            <linearGradient id="{$id}" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="{$from}"/>
              <stop offset="100%" stop-color="{$to}"/>
            </linearGradient>
          </defs>
          <rect width="128" height="128" fill="url(#{$id})"/>
          <text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff"
                font-family="Segoe UI, Helvetica, Arial, sans-serif" font-size="52" font-weight="600"
                letter-spacing="1">{$initials}</text>
        </svg>
        SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
