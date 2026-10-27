<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-url" content="{{ rtrim(url("/"), "/") }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="robots" content="noindex">

    <title>@yield('title', 'Dashboard') &middot; SaverMate Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
</head>
<body class="adm-body">
@php
    $route = request()->route()->getName();
    $type = request()->route('type');
    $nav = [
        'Overview' => [
            ['admin.dashboard', [], 'Dashboard', 'feed', $route === 'admin.dashboard'],
        ],
        'Members' => [
            ['admin.users.index', [], 'All members', 'people', str_starts_with($route, 'admin.users')],
        ],
        'Content' => [
            ['admin.content.index', ['type' => 'posts'], 'Posts', 'pencil', $type === 'posts'],
            ['admin.content.index', ['type' => 'comments'], 'Post comments', 'comment', $type === 'comments'],
            ['admin.content.index', ['type' => 'movies'], 'Movies', 'film', $type === 'movies'],
            ['admin.content.index', ['type' => 'movie-comments'], 'Movie comments', 'captions', $type === 'movie-comments'],
            ['admin.content.index', ['type' => 'songs'], 'Songs', 'music', $type === 'songs'],
            ['admin.content.index', ['type' => 'files'], 'Drive files', 'cloud', $type === 'files'],
        ],
        'System' => [
            ['admin.settings', [], 'Settings & broadcast', 'settings', $route === 'admin.settings'],
            ['admin.activity', [], 'Audit log', 'clock', $route === 'admin.activity'],
        ],
    ];
@endphp

<div class="adm" data-adm>
    <aside class="adm-side" aria-label="Admin navigation">
        <a class="brand adm-brand" href="{{ route('admin.dashboard') }}">
            <span class="brand-mark" aria-hidden="true"><x-icon name="shield" :size="19" /></span>
            <span>SaverMate<span class="brand-sub">Admin panel</span></span>
        </a>

        <nav class="adm-nav">
            @foreach ($nav as $group => $links)
                <div class="adm-nav-group">{{ $group }}</div>
                @foreach ($links as [$name, $params, $label, $icon, $active])
                    <a class="adm-link {{ $active ? 'is-active' : '' }}" href="{{ route($name, $params) }}">
                        <x-icon :name="$icon" :size="18" /> <span>{{ $label }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        <a class="adm-link adm-back" href="{{ route('feed.index') }}">
            <x-icon name="back" :size="18" /> <span>Back to the network</span>
        </a>
    </aside>

    <div class="adm-scrim" data-adm-close></div>

    <div class="adm-main">
        <header class="adm-top">
            <button class="adm-menu" type="button" data-adm-toggle aria-label="Open navigation">
                <x-icon name="menu" :size="20" />
            </button>

            <div class="adm-title">
                @hasSection('crumb')
                    <div class="adm-crumb">@yield('crumb')</div>
                @endif
                <h1>@yield('title', 'Dashboard')</h1>
            </div>

            <div class="adm-actions">@yield('actions')</div>

            <a class="adm-me" href="{{ route('profile.show', auth()->user()) }}" title="Your profile">
                <x-avatar :user="auth()->user()" :size="34" />
            </a>
        </header>

        <main class="adm-content" id="main">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>

@include('partials.mini-player')

<script src="{{ asset('js/player-sync.js') }}?v={{ filemtime(public_path('js/player-sync.js')) }}" defer></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
<script src="{{ asset('js/miniplayer.js') }}?v={{ filemtime(public_path('js/miniplayer.js')) }}" defer></script>
<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}" defer></script>
</body>
</html>
