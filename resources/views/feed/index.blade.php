@extends('layouts.app')

@section('title', 'Newsfeed')

@section('content')
    @guest
        <section class="welcome">
            <h1>What your colleagues are working on today</h1>
            <p>
                SaverMate is the social layer of your local network: a shared newsfeed, a searchable
                colleague directory, private chat, and a cloud drive that only leaves the building
                when you say so. Read the feed as a guest, or join in.
            </p>
            <div class="welcome-actions">
                <a class="btn btn-lg btn-white" href="{{ route('register') }}" data-auth-open="register">Create your account</a>
                <a class="btn btn-lg btn-outline-white" href="{{ route('login') }}" data-auth-open="login">Sign in</a>
            </div>

            <div class="welcome-stats">
                <div>
                    <b>{{ number_format($memberCount) }}</b>
                    <span>{{ \Illuminate\Support\Str::plural('member', $memberCount) }}</span>
                </div>
                <div>
                    <b>{{ number_format($postCount) }}</b>
                    <span>{{ \Illuminate\Support\Str::plural('post', $postCount) }}</span>
                </div>
                <div>
                    <b data-online-tally>{{ $onlineMembers->count() }}</b>
                    <span>online now</span>
                </div>
            </div>
        </section>
    @else
        @include('feed.partials.composer')
    @endguest

    {{-- Infinite scroll: app.js fetches the next page and appends its posts here. --}}
    <div class="feed-list" data-feed-list>
        @foreach ($posts as $post)
            @include('feed.partials.post', ['post' => $post])
        @endforeach
    </div>

    @if ($posts->isEmpty())
        <div class="card card-pad empty">
            <div class="em-icon"><x-icon name="sparkle" :size="34" /></div>
            <h3>The newsfeed is empty</h3>
            <p>
                @auth
                    Be the first to post. Share a note, a photo, or a colored card from the box above.
                @else
                    Nothing has been shared yet. Sign in to publish the first post.
                @endauth
            </p>
        </div>
    @endif

    @if ($posts->isNotEmpty())
        <div class="feed-more" data-feed-more data-next="{{ $posts->nextPageUrl() }}">
            <div class="feed-loading" data-feed-loading hidden>
                <span class="feed-spinner" aria-hidden="true"></span> Loading more posts&hellip;
            </div>
            <button class="btn btn-ghost btn-sm" type="button" data-feed-retry hidden>Could not load more posts. Try again</button>
            <p class="small muted" data-feed-end @if ($posts->hasMorePages()) hidden @endif>
                <x-icon name="check" :size="15" /> You're all caught up
            </p>
        </div>

        {{-- Plain page links for browsers without JavaScript; hidden once infinite scroll starts. --}}
        <div data-feed-pager>
            @include('partials.pager', ['paginator' => $posts])
        </div>
    @endif
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="people" :size="18" />
            <h3>Online now</h3>
            <span class="tally" data-online-tally>{{ $onlineMembers->count() }}</span>
        </div>

        @auth
            <div data-online-list>
                @forelse ($onlineMembers as $member)
                    <a class="person-row" href="{{ route('chat.with', $member) }}">
                        <x-avatar :user="$member" :size="34" :presence="true" />
                        <span class="pr-body">
                            <span class="pr-name">{{ $member->name }}</span>
                            <span class="pr-meta">{{ $member->designation ?: '@'.$member->username }}</span>
                        </span>
                        <span class="pr-action"><x-icon name="chat" :size="16" /></span>
                    </a>
                @empty
                    <p class="small muted" style="padding:.4rem .35rem">No colleagues online right now.</p>
                @endforelse
            </div>
        @else
            <p class="small muted">
                Sign in to see who is active and start a private chat.
            </p>
        @endauth
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="globe" :size="18" />
            <h3>The network</h3>
        </div>

        <div class="stat-strip" style="border:1px solid var(--line);border-radius:var(--r-md);overflow:hidden">
            <div class="stat">
                <b>{{ number_format($memberCount) }}</b>
                <span>{{ \Illuminate\Support\Str::plural('Member', $memberCount) }}</span>
            </div>
            <div class="stat">
                <b>{{ number_format($postCount) }}</b>
                <span>{{ \Illuminate\Support\Str::plural('Post', $postCount) }}</span>
            </div>
        </div>

        <a class="btn btn-ghost btn-sm btn-block" href="{{ route('drive.public') }}" style="margin-top:.8rem">
            <x-icon name="globe" :size="15" /> Open the network drive
        </a>
    </section>
@endsection
