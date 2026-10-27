@extends('layouts.app')

@section('title', $profile->name)

@section('content')
    @php
        $isMe = auth()->check() && auth()->id() === $profile->id;
        $skills = $profile->skillList();
        $firstName = \Illuminate\Support\Str::of($profile->name)->explode(' ')->first();
        $hasShared = $sharedRoots->isNotEmpty() || $looseFiles->isNotEmpty();
    @endphp

    <section class="profile-hero">
        <div class="profile-cover cover-fade"
             @if ($profile->coverUrl()) style="background-image:url('{{ $profile->coverUrl() }}')" @endif></div>

        <div class="profile-id">
            <x-avatar :user="$profile" :size="120" :presence="true" />

            <div class="pi-body">
                <h1>{{ $profile->name }}</h1>
                @if ($profile->designation)
                    <div class="pi-role">{{ $profile->designation }}</div>
                @endif
                <div class="pi-handle">&#64;{{ $profile->username }}</div>
                <div style="margin-top:.5rem">
                    @if ($profile->isOnline())
                        <span class="online-pill">Online now</span>
                    @else
                        <span class="offline-pill">{{ $profile->lastSeenLabel() }}</span>
                    @endif
                </div>
            </div>

            <div class="pi-actions">
                @if ($isMe)
                    <a class="btn btn-ghost" href="{{ route('profile.edit') }}">
                        <x-icon name="settings" :size="16" /> Edit profile
                    </a>
                    <a class="btn btn-ghost" href="{{ route('drive.index') }}">
                        <x-icon name="cloud" :size="16" /> My drive
                    </a>
                @elseif (auth()->check())
                    <a class="btn" href="{{ route('chat.with', $profile) }}">
                        <x-icon name="chat" :size="16" /> Send a message
                    </a>
                @else
                    <a class="btn" href="{{ route('login') }}" data-auth-open="login">Sign in to message</a>
                @endif
            </div>
        </div>
    </section>

    {{-- Tabs. Shared files sits right after the feed and carries the count. --}}
    <div class="tabs" data-tabs role="tablist" aria-label="Profile sections">
        <button class="tab is-active" type="button" role="tab" data-tab="feed" aria-selected="true">
            <x-icon name="feed" :size="16" /> Feed
            @if ($stats['posts'])<span class="tab-count">{{ $stats['posts'] }}</span>@endif
        </button>
        <button class="tab" type="button" role="tab" data-tab="shared" aria-selected="false" tabindex="-1">
            <x-icon name="folder" :size="16" /> Shared files
            @if ($sharedFileCount)<span class="tab-count">{{ $sharedFileCount }}</span>@endif
        </button>
        <button class="tab" type="button" role="tab" data-tab="about" aria-selected="false" tabindex="-1">
            <x-icon name="user" :size="16" /> About
        </button>
        <button class="tab" type="button" role="tab" data-tab="skills" aria-selected="false" tabindex="-1">
            <x-icon name="sparkle" :size="16" /> Skills
            @if ($skills)<span class="tab-count">{{ count($skills) }}</span>@endif
        </button>
        <button class="tab" type="button" role="tab" data-tab="stats" aria-selected="false" tabindex="-1">
            <x-icon name="sparkle" :size="16" /> Statistics
        </button>
    </div>

    {{-- Feed ------------------------------------------------------------- --}}
    <div data-panel="feed" role="tabpanel">
        <div>
            @forelse ($posts as $post)
                @include('feed.partials.post', ['post' => $post])
            @empty
                <div class="card card-pad empty">
                    <div class="em-icon"><x-icon name="feed" :size="32" /></div>
                    <h3>No posts yet</h3>
                    <p>
                        @if ($isMe)
                            Your updates will appear here once you publish something.
                        @else
                            {{ $firstName }} has not posted yet.
                        @endif
                    </p>
                    @if ($isMe)
                        <a class="btn btn-sm" href="{{ route('feed.index') }}">Write your first post</a>
                    @endif
                </div>
            @endforelse

            @include('partials.pager', ['paginator' => $posts])
        </div>
    </div>

    {{-- Shared files ------------------------------------------------------ --}}
    <div data-panel="shared" role="tabpanel" hidden>
        @if (! $hasShared)
            <div class="card card-pad empty">
                <div class="em-icon"><x-icon name="folder" :size="32" /></div>
                <h3>Nothing shared yet</h3>
                <p>
                    @if ($isMe)
                        Files in your drive stay private until you flip them to Public.
                        Anything you publish is listed here for colleagues to download.
                    @else
                        {{ $firstName }} has not published any files yet.
                    @endif
                </p>
                @if ($isMe)
                    <a class="btn btn-sm" href="{{ route('drive.index') }}">
                        <x-icon name="cloud" :size="15" /> Open my drive
                    </a>
                @endif
            </div>
        @else
            <div class="shared-browser" data-shared>
                <div class="shared-toolbar">
                    <div class="shared-search">
                        <span class="si"><x-icon name="search" :size="17" /></span>
                        <input type="search" data-shared-search autocomplete="off"
                               placeholder="Search {{ $isMe ? 'your' : $firstName."'s" }} shared files and folders"
                               aria-label="Search shared files">
                    </div>

                    <div class="shared-tools">
                        <button class="btn btn-ghost btn-sm" type="button" data-shared-expand>
                            <x-icon name="plus" :size="15" /> Expand all
                        </button>
                        <button class="btn btn-ghost btn-sm" type="button" data-shared-collapse>
                            <x-icon name="close" :size="15" /> Collapse all
                        </button>
                    </div>
                </div>

                <p class="small muted shared-summary">
                    <span data-shared-summary>
                        {{ $sharedFolderCount }} {{ \Illuminate\Support\Str::plural('folder', $sharedFolderCount) }},
                        {{ $sharedFileCount }} {{ \Illuminate\Support\Str::plural('file', $sharedFileCount) }},
                        {{ \App\Models\DriveFile::humanSize($sharedSize) }}
                    </span>
                    @if ($isMe)
                        &middot; <a href="{{ route('drive.index') }}">Manage in your drive</a>
                    @endif
                </p>

                <div class="tree" data-shared-tree>
                    @foreach ($sharedRoots as $folder)
                        @include('profile.partials.shared-folder', ['folder' => $folder, 'depth' => 0])
                    @endforeach

                    {{-- Public files that sit outside any public folder still need a home. --}}
                    @if ($looseFiles->isNotEmpty())
                        <div class="tree-node" data-tree-folder data-name="Shared individually">
                            <button class="tree-head" type="button" data-tree-toggle aria-expanded="true">
                                <span class="tree-caret" aria-hidden="true"><x-icon name="next" :size="14" /></span>
                                <span class="tree-icon"><x-icon name="folder" :size="18" /></span>
                                <span class="tree-label">
                                    <span class="tree-name">Shared individually</span>
                                    <span class="tree-meta">
                                        {{ $looseFiles->count() }} {{ \Illuminate\Support\Str::plural('file', $looseFiles->count()) }}
                                        &middot; {{ \App\Models\DriveFile::humanSize((int) $looseFiles->sum('size')) }}
                                    </span>
                                </span>
                                <span class="badge-public"><x-icon name="globe" :size="12" /> Public</span>
                            </button>

                            <div class="tree-body">
                                @foreach ($looseFiles as $file)
                                    @include('profile.partials.shared-file', ['file' => $file])
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="empty" data-shared-empty hidden style="padding:2.5rem 1rem">
                    <div class="em-icon"><x-icon name="search" :size="30" /></div>
                    <h3 style="font-size:1rem">No match</h3>
                    <p class="small">Nothing here matches <b data-shared-term></b>. Try part of a file name.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- About ------------------------------------------------------------ --}}
    <div data-panel="about" role="tabpanel" hidden>
        <div class="card card-pad">
            <h2 style="margin-bottom:1.1rem">About {{ $firstName }}</h2>

            <dl class="detail-list">
                <div>
                    <dt>Bio</dt>
                    <dd>{{ $profile->bio ?: ($isMe ? 'You have not written a bio yet.' : 'No bio added yet.') }}</dd>
                </div>
                <div>
                    <dt>Designation or job role</dt>
                    <dd>{{ $profile->designation ?: 'Not added yet' }}</dd>
                </div>
                <div>
                    <dt>Qualifications</dt>
                    <dd>{{ $profile->qualifications ?: 'Not added yet' }}</dd>
                </div>
                <div>
                    <dt>Username</dt>
                    <dd>&#64;{{ $profile->username }}</dd>
                </div>
                <div>
                    <dt>Joined the network</dt>
                    <dd>{{ $profile->created_at->format('j F Y') }}</dd>
                </div>
                <div>
                    <dt>Last active</dt>
                    <dd>{{ $profile->lastSeenLabel() }}</dd>
                </div>
            </dl>

            @if ($isMe)
                <a class="btn btn-ghost btn-sm" href="{{ route('profile.edit') }}" style="margin-top:1.2rem">
                    <x-icon name="pencil" :size="15" /> Edit these details
                </a>
            @endif
        </div>
    </div>

    {{-- Skills ----------------------------------------------------------- --}}
    <div data-panel="skills" role="tabpanel" hidden>
        <div class="card card-pad">
            <h2>Skills</h2>
            <p class="muted small" style="margin-top:.3rem">
                Each one links to everyone on the network with that skill.
            </p>

            @if ($skills)
                <div class="chip-row" style="justify-content:flex-start;margin-top:1.1rem">
                    @foreach ($skills as $skill)
                        <a class="chip" href="{{ route('directory.index', ['q' => $skill]) }}">{{ $skill }}</a>
                    @endforeach
                </div>
            @else
                <div class="empty" style="padding:2rem 1rem">
                    <div class="em-icon"><x-icon name="sparkle" :size="30" /></div>
                    <h3 style="font-size:1rem">No skills listed</h3>
                    <p class="small">
                        @if ($isMe)
                            Add a comma separated list so colleagues can find you by what you do.
                        @else
                            {{ $firstName }} has not listed any skills yet.
                        @endif
                    </p>
                    @if ($isMe)
                        <a class="btn btn-sm" href="{{ route('profile.edit') }}">Add your skills</a>
                    @endif
                </div>
            @endif

            @if ($profile->qualifications)
                <div style="margin-top:1.6rem;padding-top:1.3rem;border-top:1px solid var(--line-soft)">
                    <h3 style="font-size:1rem">Qualifications</h3>
                    <p class="muted" style="margin-top:.4rem;white-space:pre-wrap">{{ $profile->qualifications }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Statistics -------------------------------------------------------- --}}
    <div data-panel="stats" role="tabpanel" hidden>
        <div>
            <div class="stat-grid">
                <div class="stat-card">
                    <span class="sc-icon" style="background:var(--jade-wash);color:var(--jade-dark)">
                        <x-icon name="feed" :size="18" />
                    </span>
                    <b>{{ number_format($stats['posts']) }}</b>
                    <span class="sc-label">{{ \Illuminate\Support\Str::plural('Post', $stats['posts']) }} published</span>
                </div>

                <div class="stat-card">
                    <span class="sc-icon" style="background:var(--coral-wash);color:#b23c32">
                        <x-icon name="sparkle" :size="18" />
                    </span>
                    <b>{{ number_format($stats['reactions_received']) }}</b>
                    <span class="sc-label">Reactions received</span>
                </div>

                <div class="stat-card">
                    <span class="sc-icon" style="background:#eef2ff;color:#4f46e5">
                        <x-icon name="comment" :size="18" />
                    </span>
                    <b>{{ number_format($stats['comments']) }}</b>
                    <span class="sc-label">{{ \Illuminate\Support\Str::plural('Comment', $stats['comments']) }} written</span>
                </div>

                <div class="stat-card">
                    <span class="sc-icon" style="background:#fdf2f8;color:#be185d">
                        <x-icon name="sparkle" :size="18" />
                    </span>
                    <b>{{ number_format($stats['reactions_given']) }}</b>
                    <span class="sc-label">Reactions given</span>
                </div>

                <div class="stat-card">
                    <span class="sc-icon" style="background:var(--amber-wash);color:#96610f">
                        <x-icon name="folder" :size="18" />
                    </span>
                    <b>{{ number_format($sharedFileCount) }}</b>
                    <span class="sc-label">
                        {{ \Illuminate\Support\Str::plural('File', $sharedFileCount) }} shared
                        @if ($sharedFolderCount)
                            in {{ $sharedFolderCount }} {{ \Illuminate\Support\Str::plural('folder', $sharedFolderCount) }}
                        @endif
                    </span>
                </div>

                <div class="stat-card">
                    <span class="sc-icon" style="background:#ecfeff;color:#0e7490">
                        <x-icon name="cloud" :size="18" />
                    </span>
                    <b>{{ \App\Models\DriveFile::humanSize($sharedSize) }}</b>
                    <span class="sc-label">Shared with the network</span>
                </div>
            </div>

            <div class="card card-pad" style="margin-top:1.25rem">
                <h3 style="font-size:1rem;margin-bottom:.9rem">Membership</h3>
                <dl class="detail-list">
                    <div>
                        <dt>Joined the network</dt>
                        <dd>{{ $profile->created_at->format('j F Y') }} ({{ $profile->created_at->diffForHumans() }})</dd>
                    </div>
                    <div>
                        <dt>Last active</dt>
                        <dd>{{ $profile->lastSeenLabel() }}</dd>
                    </div>
                    @if ($isMe)
                        <div>
                            <dt>Files in your drive</dt>
                            {{-- dd is pre-wrap for bios, so keep this on one line. --}}
                            <dd>{{ number_format($stats['files']) }} total, {{ number_format($sharedFileCount) }} published to the network</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="user" :size="18" />
            <h3>About {{ $firstName }}</h3>
        </div>

        @if ($profile->bio)
            <p class="small" style="color:var(--ink-soft)">{{ \Illuminate\Support\Str::limit($profile->bio, 220) }}</p>
        @endif

        <dl class="kv-list">
            <div><dt>Role</dt><dd>{{ $profile->designation ?: 'Not added' }}</dd></div>
            <div><dt>Joined</dt><dd>{{ $profile->created_at->format('j M Y') }}</dd></div>
            <div><dt>Last active</dt><dd>{{ $profile->isOnline() ? 'Online now' : $profile->lastSeenLabel() }}</dd></div>
        </dl>
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="sparkle" :size="18" />
            <h3>Activity</h3>
        </div>

        <dl class="kv-list">
            <div><dt>{{ \Illuminate\Support\Str::plural('Post', $stats['posts']) }}</dt><dd>{{ number_format($stats['posts']) }}</dd></div>
            <div><dt>Reactions received</dt><dd>{{ number_format($stats['reactions_received']) }}</dd></div>
            <div><dt>{{ \Illuminate\Support\Str::plural('Comment', $stats['comments']) }}</dt><dd>{{ number_format($stats['comments']) }}</dd></div>
            <div><dt>Files shared</dt><dd>{{ number_format($sharedFileCount) }}</dd></div>
        </dl>

        @if ($isMe)
            <a class="btn btn-ghost btn-sm btn-block" href="{{ route('profile.edit') }}" style="margin-top:.85rem">
                <x-icon name="settings" :size="15" /> Edit profile
            </a>
        @elseif (auth()->check())
            <a class="btn btn-sm btn-block" href="{{ route('chat.with', $profile) }}" style="margin-top:.85rem">
                <x-icon name="chat" :size="15" /> Send a message
            </a>
        @endif
    </section>
@endsection
