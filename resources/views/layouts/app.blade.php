<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-url" content="{{ rtrim(url("/"), "/") }}">
    <meta name="user-id" content="{{ auth()->id() }}">

    <title>@yield('title', 'Newsfeed') &middot; SaverMate</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @stack('styles')
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

@include('partials.topbar')
@include('partials.announcement')

{{-- Every page: navigation tabs on the left, the page itself in a white panel,
     and a right column with a page-specific panel and the recent members list. --}}
<div class="shell @yield('shell-class')">
    {{-- A page can replace the navigation rail with its own left column (chat does). --}}
    @hasSection('left')
        <aside class="left-col" aria-label="@yield('left-label', 'Sidebar')">
            @yield('left')
        </aside>
    @else
        @include('partials.rail')
    @endif

    <main class="content" id="main">
        @include('partials.flash')
        <div class="main-panel @yield('main-class')">
            @yield('content')
        </div>
    </main>

    @hasSection('aside')
        <aside class="aside" aria-label="Page details">
            <div class="side-panel">
                @yield('aside')
            </div>

            @include('partials.members')
        </aside>
    @endif
</div>

@auth
    @include('partials.tabbar')
@else
    @include('partials.auth-modal')
@endauth

@include('partials.mini-player')

<script src="{{ asset('js/player-sync.js') }}?v={{ filemtime(public_path('js/player-sync.js')) }}" defer></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
<script src="{{ asset('js/miniplayer.js') }}?v={{ filemtime(public_path('js/miniplayer.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
