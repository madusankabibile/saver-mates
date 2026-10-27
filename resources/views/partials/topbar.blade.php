@php
    $me = auth()->user();
@endphp

<header class="topbar">
    <a class="brand" href="{{ route('feed.index') }}">
        <span class="brand-mark" aria-hidden="true">
            <x-icon name="sparkle" :size="19" />
        </span>
        <span class="brand-text">
            SaverMate
            <span class="brand-sub">Local network</span>
        </span>
    </a>

    @auth
        <form class="topbar-search" action="{{ route('directory.index') }}" method="GET" role="search">
            <input type="search" name="q" value="{{ request('q') }}"
                   placeholder="Search colleagues by name or job role" aria-label="Search colleagues">
            <span class="search-icon"><x-icon name="search" :size="17" /></span>
        </form>
    @else
        <form class="topbar-search" action="{{ route('drive.public') }}" method="GET" role="search">
            <input type="search" name="q" value="{{ request('q') }}"
                   placeholder="Search shared files on the network" aria-label="Search the network drive">
            <span class="search-icon"><x-icon name="search" :size="17" /></span>
        </form>
    @endauth

    <div class="topbar-actions">
        @auth
            <a class="icon-btn" href="{{ route('chat.index') }}" title="Messages" aria-label="Messages">
                <x-icon name="chat" :size="19" />
                <span class="count-dot" data-chat-count hidden>0</span>
            </a>

            <div class="dropdown" data-dropdown="alerts">
                <button class="icon-btn" type="button" data-dropdown-toggle aria-expanded="false"
                        title="Notifications" aria-label="Notifications">
                    <x-icon name="bell" :size="19" />
                    <span class="count-dot" data-alert-count hidden>0</span>
                </button>

                <div class="dropdown-panel" data-dropdown-panel hidden>
                    <div class="dropdown-head">
                        <h3>Notifications</h3>
                        <button class="btn btn-ghost btn-sm" type="button" onclick="markAlertsRead()">Mark all read</button>
                    </div>
                    <div class="dropdown-list" data-alert-list>
                        <p class="small muted center" style="padding:1.2rem">Loading&hellip;</p>
                    </div>
                    <div class="dropdown-foot">
                        <a href="{{ route('alerts.index') }}">See all notifications</a>
                    </div>
                </div>
            </div>

            <div class="dropdown" data-dropdown="me">
                <button class="topbar-user" type="button" data-dropdown-toggle aria-expanded="false">
                    <img src="{{ $me->avatarUrl() }}" alt="">
                    <span>{{ \Illuminate\Support\Str::of($me->name)->explode(' ')->first() }}</span>
                </button>

                <div class="dropdown-panel" data-dropdown-panel hidden style="width:270px">
                    <div class="dropdown-head" style="gap:.7rem">
                        <x-avatar :user="$me" :size="42" />
                        <div style="min-width:0">
                            <div class="strong" style="font-family:var(--font-display)">{{ $me->name }}</div>
                            <div class="tiny muted">&#64;{{ $me->username }}</div>
                        </div>
                    </div>

                    <div style="padding:.35rem 0">
                        <a class="menu-link" href="{{ route('profile.show', $me) }}">
                            <span class="mi"><x-icon name="user" :size="17" /></span> My profile
                        </a>
                        <a class="menu-link" href="{{ route('profile.edit') }}">
                            <span class="mi"><x-icon name="settings" :size="17" /></span> Edit profile
                        </a>
                        <a class="menu-link" href="{{ route('drive.index') }}">
                            <span class="mi"><x-icon name="cloud" :size="17" /></span> My drive
                        </a>
                        @if ($me->isAdmin())
                            <a class="menu-link" href="{{ route('admin.dashboard') }}">
                                <span class="mi"><x-icon name="shield" :size="17" /></span> Admin panel
                            </a>
                        @endif
                        <div class="menu-sep"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="menu-link danger" type="submit" style="width:100%;border:none;background:none;font:inherit;cursor:pointer;text-align:left">
                                <span class="mi"><x-icon name="logout" :size="17" /></span> Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <a class="topbar-ghost" href="{{ route('login') }}" data-auth-open="login">Sign in</a>
            <a class="topbar-cta" href="{{ route('register') }}" data-auth-open="register">Join the network</a>
        @endauth
    </div>
</header>
