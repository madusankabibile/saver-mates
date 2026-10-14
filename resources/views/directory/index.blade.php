@extends('layouts.app')

@section('title', 'Colleagues')

@section('content')
    <div class="page-head">
        <div>
            <h1>Colleagues</h1>
            <p>
                {{ number_format($totalCount) }} {{ \Illuminate\Support\Str::plural('member', $totalCount) }} on the network,
                <span class="strong" style="color:var(--jade-dark)"><span data-online-tally>{{ $onlineCount }}</span> online right now</span>.
            </p>
        </div>
    </div>

    <div class="filter-bar">
        <form class="search-form" method="GET" action="{{ route('directory.index') }}" role="search">
            <span class="si"><x-icon name="search" :size="17" /></span>
            <input type="search" name="q" value="{{ $term }}"
                   placeholder="Search by name, username, job role or skill" aria-label="Search colleagues">
            @if ($filter !== 'all')
                <input type="hidden" name="filter" value="{{ $filter }}">
            @endif
        </form>

        <div class="seg">
            <a class="{{ $filter === 'all' ? 'is-active' : '' }}"
               href="{{ route('directory.index', array_filter(['q' => $term])) }}">Everyone</a>
            <a class="{{ $filter === 'online' ? 'is-active' : '' }}"
               href="{{ route('directory.index', array_filter(['q' => $term, 'filter' => 'online'])) }}">Online now</a>
        </div>

        @if ($term)
            <a class="btn btn-ghost btn-sm" href="{{ route('directory.index') }}">
                <x-icon name="close" :size="15" /> Clear search
            </a>
        @endif
    </div>

    @if ($term)
        <p class="small muted" style="margin:-.5rem 0 1rem">
            {{ $members->total() }} {{ \Illuminate\Support\Str::plural('result', $members->total()) }}
            for &ldquo;{{ $term }}&rdquo;
        </p>
    @endif

    @if ($members->isEmpty())
        <div class="card card-pad empty">
            <div class="em-icon"><x-icon name="people" :size="32" /></div>
            <h3>{{ $term ? 'Nobody matches that search' : 'No colleagues to show' }}</h3>
            <p>
                @if ($term)
                    Try a shorter search, or look for a job role such as &ldquo;officer&rdquo; or a skill.
                @elseif ($filter === 'online')
                    Nobody else is active at the moment. Switch to Everyone to see the full directory.
                @else
                    You are the first member here. Invite your colleagues to register.
                @endif
            </p>
            @if ($term || $filter !== 'all')
                <a class="btn btn-sm" href="{{ route('directory.index') }}">Show everyone</a>
            @endif
        </div>
    @else
        <div class="member-grid">
            @foreach ($members as $member)
                <article class="member-card">
                    <a href="{{ route('profile.show', $member) }}">
                        <x-avatar :user="$member" :size="72" :presence="true" />
                    </a>

                    <a class="mc-name" href="{{ route('profile.show', $member) }}">{{ $member->name }}</a>
                    <div class="mc-role">{{ $member->designation ?: '@'.$member->username }}</div>

                    <div class="mc-status">
                        @if ($member->isOnline())
                            <span class="online-pill">Online now</span>
                        @else
                            <span class="offline-pill">{{ $member->lastSeenLabel() }}</span>
                        @endif
                    </div>

                    @if ($member->skillList())
                        <div class="chip-row">
                            @foreach (array_slice($member->skillList(), 0, 3) as $skill)
                                <span class="chip">{{ $skill }}</span>
                            @endforeach
                            @if (count($member->skillList()) > 3)
                                <span class="chip chip-plain">+{{ count($member->skillList()) - 3 }}</span>
                            @endif
                        </div>
                    @endif

                    <div class="mc-actions">
                        <a class="btn btn-sm" href="{{ route('chat.with', $member) }}">
                            <x-icon name="chat" :size="15" /> Chat
                        </a>
                        <a class="btn btn-ghost btn-sm" href="{{ route('profile.show', $member) }}">Profile</a>
                    </div>
                </article>
            @endforeach
        </div>

        @include('partials.pager', ['paginator' => $members])
    @endif
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="people" :size="18" />
            <h3>Directory</h3>
        </div>

        <dl class="kv-list">
            <div><dt>Members</dt><dd>{{ number_format($totalCount) }}</dd></div>
            <div><dt>Online now</dt><dd data-online-tally>{{ $onlineCount }}</dd></div>
            @if ($term)
                <div><dt>Matching search</dt><dd>{{ number_format($members->total()) }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="search" :size="18" />
            <h3>Search tips</h3>
        </div>
        <p class="small muted">
            Search matches names, usernames, job roles and skills. Try a role such as
            &ldquo;officer&rdquo; or a skill like &ldquo;Excel&rdquo;.
        </p>
        <a class="btn btn-ghost btn-sm btn-block" href="{{ route('directory.index', ['filter' => 'online']) }}">
            Show who is online
        </a>
    </section>
@endsection
