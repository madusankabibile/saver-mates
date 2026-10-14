@extends('layouts.admin')

@section('title', 'Dashboard')

@section('actions')
    <a class="btn btn-ghost btn-sm" href="{{ route('admin.users.export') }}"><x-icon name="download" :size="15" /> Export members</a>
    <a class="btn btn-sm" href="{{ route('admin.settings') }}#broadcast"><x-icon name="bell" :size="15" /> Send announcement</a>
@endsection

@php
    use App\Models\DriveFile;
    use Illuminate\Support\Str;

    $signupTotal = array_sum(array_column($signups, 'count'));
    $totals = [
        'posts' => array_sum(array_column($activity, 'posts')),
        'comments' => array_sum(array_column($activity, 'comments')),
        'messages' => array_sum(array_column($activity, 'messages')),
    ];

    $kpis = [
        ['Members', $stats['users'], 'people', '<b>+'.$stats['users_new'].'</b> this week', route('admin.users.index')],
        ['Online now', $stats['online'], 'globe', $stats['active_today'].' active in 24h', route('admin.users.index', ['filter' => 'online'])],
        ['Posts', $stats['posts'], 'feed', '<b>+'.$stats['posts_new'].'</b> this week', route('admin.content.index', 'posts')],
        ['Comments', $stats['comments'], 'comment', number_format($stats['reactions']).' reactions', route('admin.content.index', 'comments')],
        ['Chat messages', $stats['messages'], 'chat', '<b>+'.$stats['messages_new'].'</b> this week', null],
        ['Movies', $stats['movies'], 'film', number_format($stats['movie_views']).' views', route('admin.content.index', 'movies')],
        ['Songs', $stats['songs'], 'music', number_format($stats['song_plays']).' plays &middot; '.$stats['playlists'].' playlists', route('admin.content.index', 'songs')],
        ['Drive files', $stats['files'], 'cloud', $stats['files_public'].' on the network drive', route('admin.content.index', 'files')],
    ];
@endphp

@section('content')
    @if ($stats['suspended'])
        <div class="notice">
            {{ $stats['suspended'].' '.Str::plural('member', $stats['suspended']) }} currently suspended.
            <a href="{{ route('admin.users.index', ['filter' => 'suspended']) }}">Review suspensions</a>
        </div>
    @endif

    <div class="kpis">
        @foreach ($kpis as [$label, $value, $icon, $sub, $href])
            <{{ $href ? 'a href='.$href : 'div' }} class="kpi">
                <span class="kpi-label"><x-icon :name="$icon" :size="16" /> {{ $label }}</span>
                <span class="kpi-value">{{ number_format($value) }}</span>
                <span class="kpi-sub">{!! $sub !!}</span>
            </{{ $href ? 'a' : 'div' }}>
        @endforeach
    </div>

    <div class="adm-grid adm-grid-2 adm-section">
        <section class="adm-card">
            <div class="adm-card-head">
                <h2>New members per day</h2>
                <span class="muted">{{ $signupTotal }} in 30 days</span>
            </div>
            @include('admin.partials.bars', [
                'aria' => 'New members per day over the last 30 days',
                'axisEvery' => 7,
                'columns' => array_map(fn ($d) => [
                    'label' => $d['date']->format('j M'),
                    'tip' => $d['date']->format('D j M').'|'.$d['count'].' new '.Str::plural('member', $d['count']),
                    'segments' => [['class' => 's-posts', 'value' => $d['count']]],
                ], $signups),
            ])
        </section>

        <section class="adm-card">
            <div class="adm-card-head">
                <h2>Activity per day</h2>
                <span class="muted">last 14 days</span>
            </div>
            @include('admin.partials.bars', [
                'aria' => 'Posts, comments and chat messages per day over the last 14 days',
                'axisEvery' => 2,
                'columns' => array_map(fn ($d) => [
                    'label' => $d['date']->format('j M'),
                    'tip' => $d['date']->format('D j M').'|Posts: '.$d['posts'].'|Comments: '.$d['comments'].'|Messages: '.$d['messages'],
                    'segments' => [
                        ['class' => 's-posts', 'value' => $d['posts']],
                        ['class' => 's-comments', 'value' => $d['comments']],
                        ['class' => 's-messages', 'value' => $d['messages']],
                    ],
                ], $activity),
            ])
            <div class="legend">
                <span><i class="s-posts"></i> Posts <b>{{ $totals['posts'] }}</b></span>
                <span><i class="s-comments"></i> Comments <b>{{ $totals['comments'] }}</b></span>
                <span><i class="s-messages"></i> Messages <b>{{ $totals['messages'] }}</b></span>
            </div>
        </section>
    </div>

    <div class="adm-grid adm-grid-3 adm-section">
        <section class="adm-card">
            <div class="adm-card-head">
                <h2>Storage used</h2>
                <span class="muted">{{ DriveFile::humanSize($storageTotal) }}</span>
            </div>
            <div class="meter" role="img" aria-label="Storage split by type">
                @foreach ($storage as $s)
                    @if ($s['bytes'] > 0)
                        <i class="tone-{{ $s['tone'] }}" style="flex: {{ $s['bytes'] }}" title="{{ $s['label'] }}: {{ DriveFile::humanSize($s['bytes']) }}"></i>
                    @endif
                @endforeach
            </div>
            <ul class="meter-rows">
                @foreach ($storage as $s)
                    <li>
                        <i class="tone-{{ $s['tone'] }}"></i>
                        <span>{{ $s['label'] }}</span>
                        <b>{{ DriveFile::humanSize($s['bytes']) }}</b>
                        <span class="muted small" style="flex:none;width:3.2em;text-align:right">{{ $storageTotal ? round($s['bytes'] / $storageTotal * 100) : 0 }}%</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="adm-card">
            <div class="adm-card-head"><h2>Most active posters</h2></div>
            <ul class="adm-list">
                @foreach ($topPosters as $i => $u)
                    <li>
                        <span class="rank">{{ $i + 1 }}</span>
                        <x-avatar :user="$u" :size="30" />
                        <a class="al-body" href="{{ route('admin.users.show', $u) }}">
                            <span class="al-title">{{ $u->name }}</span>
                        </a>
                        <span class="al-num">{{ $u->posts_count }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="adm-card">
            <div class="adm-card-head"><h2>Accounts</h2></div>
            <ul class="adm-list">
                <li><span class="al-body">Administrators</span><a class="al-num" href="{{ route('admin.users.index', ['filter' => 'admins']) }}">{{ $stats['admins'] }}</a></li>
                <li><span class="al-body">Suspended</span><a class="al-num" href="{{ route('admin.users.index', ['filter' => 'suspended']) }}">{{ $stats['suspended'] }}</a></li>
                <li><span class="al-body">Active in last 24 hours</span><span class="al-num">{{ $stats['active_today'] }}</span></li>
                <li>
                    <span class="al-body">Registration</span>
                    @if (\App\Models\Setting::flag('registration_open'))
                        <span class="tag tag-ok"><span class="dot"></span> Open</span>
                    @else
                        <span class="tag tag-warn"><span class="dot"></span> Closed</span>
                    @endif
                </li>
            </ul>
        </section>
    </div>

    <div class="adm-grid adm-grid-main adm-section">
        <section class="adm-card">
            <div class="adm-card-head">
                <h2>Latest posts</h2>
                <a class="small" href="{{ route('admin.content.index', 'posts') }}">Moderate posts</a>
            </div>
            <ul class="adm-list">
                @forelse ($recentPosts as $post)
                    <li>
                        <x-avatar :user="$post->user" :size="32" />
                        <div class="al-body">
                            <a class="al-title" href="{{ route('posts.show', $post) }}" target="_blank">{{ Str::limit($post->displayTitle(), 90) }}</a>
                            <span class="al-meta">{{ $post->user->name }} &middot; {{ $post->created_at->diffForHumans() }} &middot; {{ $post->reactions_count }} reactions &middot; {{ $post->comments_count }} comments</span>
                        </div>
                        <button class="mini-btn" type="button" title="Delete post"
                                data-confirm="Delete this post and its images, reactions and comments?"
                                data-action="{{ route('admin.content.destroy', ['type' => 'posts', 'id' => $post->id]) }}">
                            <x-icon name="trash" :size="15" />
                        </button>
                    </li>
                @empty
                    <li class="muted">No posts yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="adm-card">
            <div class="adm-card-head">
                <h2>Newest members</h2>
                <a class="small" href="{{ route('admin.users.index') }}">All members</a>
            </div>
            <ul class="adm-list">
                @foreach ($recentUsers as $u)
                    <li>
                        <x-avatar :user="$u" :size="32" :presence="true" />
                        <a class="al-body" href="{{ route('admin.users.show', $u) }}">
                            <span class="al-title">{{ $u->name }}</span>
                            <span class="al-meta">&#64;{{ $u->username }} &middot; joined {{ $u->created_at->diffForHumans() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="adm-grid adm-grid-3 adm-section">
        <section class="adm-card">
            <div class="adm-card-head"><h2>Top movies</h2></div>
            <ul class="adm-list">
                @forelse ($topMovies as $i => $m)
                    <li>
                        <span class="rank">{{ $i + 1 }}</span>
                        <a class="al-body" href="{{ route('movies.show', $m) }}" target="_blank"><span class="al-title">{{ $m->title }}</span></a>
                        <span class="al-num">{{ number_format($m->views) }}</span>
                    </li>
                @empty
                    <li class="muted">No movies yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="adm-card">
            <div class="adm-card-head"><h2>Top songs</h2></div>
            <ul class="adm-list">
                @forelse ($topSongs as $i => $s)
                    <li>
                        <span class="rank">{{ $i + 1 }}</span>
                        <div class="al-body"><span class="al-title">{{ $s->title }}</span><span class="al-meta">{{ $s->artistName() }}</span></div>
                        <span class="al-num">{{ number_format($s->plays) }}</span>
                    </li>
                @empty
                    <li class="muted">No songs yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="adm-card">
            <div class="adm-card-head">
                <h2>Admin activity</h2>
                <a class="small" href="{{ route('admin.activity') }}">Audit log</a>
            </div>
            <ul class="adm-list">
                @forelse ($log as $entry)
                    <li class="log-row">
                        <span class="log-dot {{ $entry->tone() }}"></span>
                        <div class="al-body">
                            <span class="small">{{ $entry->description }}</span>
                            <span class="al-meta" style="display:block">{{ $entry->user?->name ?? 'Deleted admin' }} &middot; {{ $entry->created_at->diffForHumans() }}</span>
                        </div>
                    </li>
                @empty
                    <li class="muted small">Nothing yet. Every change made here is recorded.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
