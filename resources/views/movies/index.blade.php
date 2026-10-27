@extends('layouts.app')

@section('title', 'Movies')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media.css') }}?v={{ filemtime(public_path('css/media.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/movies.js') }}?v={{ filemtime(public_path('js/movies.js')) }}" defer></script>
@endpush

@section('content')
    <div class="page-head">
        <div>
            <h1>Movies</h1>
            <p>Watch what colleagues have shared. Anyone on the network can watch.</p>
        </div>
        <div class="spacer"></div>
        @auth
            <a class="btn" href="{{ route('movies.create') }}">
                <x-icon name="upload" :size="16" /> Upload a movie
            </a>
        @endauth
    </div>

    <div class="filter-bar">
        <form class="search-form" method="GET" action="{{ route('movies.index') }}" role="search">
            <span class="si"><x-icon name="search" :size="17" /></span>
            <input type="search" name="q" value="{{ $term }}" placeholder="Search movies" aria-label="Search movies">
            @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
            @if ($sort !== 'latest')<input type="hidden" name="sort" value="{{ $sort }}">@endif
        </form>

        <div class="seg">
            @foreach (['latest' => 'Latest', 'popular' => 'Most viewed', 'liked' => 'Most liked'] as $key => $label)
                <a class="{{ $sort === $key ? 'is-active' : '' }}"
                   href="{{ route('movies.index', array_filter(['q' => $term, 'category' => $category, 'sort' => $key === 'latest' ? null : $key])) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @if ($categories->isNotEmpty())
        <nav class="chip-scroll" aria-label="Categories">
            <a class="mchip {{ ! $category ? 'is-active' : '' }}"
               href="{{ route('movies.index', array_filter(['q' => $term, 'sort' => $sort === 'latest' ? null : $sort])) }}">All</a>
            @foreach ($categories as $cat)
                <a class="mchip {{ $category === $cat ? 'is-active' : '' }}"
                   href="{{ route('movies.index', array_filter(['q' => $term, 'category' => $cat, 'sort' => $sort === 'latest' ? null : $sort])) }}">{{ $cat }}</a>
            @endforeach
        </nav>
    @endif

    {{-- Continue Watching Section --}}
    @auth
        @if ($continueWatching->isNotEmpty() && !$term && !$category)
            <section style="margin:2rem 0">
                <h2 style="font-size:1.25rem;margin-bottom:1rem;font-weight:650">
                    <x-icon name="play" :size="20" style="vertical-align:-3px" /> Continue Watching
                </h2>
                <div class="mv-grid">
                    @foreach ($continueWatching as $movie)
                        @include('movies.partials.card-progress', ['movie' => $movie])
                    @endforeach
                </div>
            </section>

            <div style="border-top:1px solid var(--border);margin:2rem 0"></div>

            <h2 style="font-size:1.25rem;margin-bottom:1rem;font-weight:650">
                All Movies
            </h2>
        @endif
    @endauth

    @if ($movies->isEmpty())
        <div class="empty" style="padding:3rem 1rem">
            <div class="em-icon"><x-icon name="film" :size="34" /></div>
            <h3>{{ $term || $category ? 'No movies match' : 'No movies yet' }}</h3>
            <p>
                @if ($term || $category)
                    Try another search or category.
                @else
                    Upload the first one and it will appear here for everyone on the network.
                @endif
            </p>
            @auth
                <a class="btn btn-sm" href="{{ route('movies.create') }}">Upload a movie</a>
            @endauth
        </div>
    @else
        <div class="mv-grid">
            @foreach ($movies as $movie)
                @include('movies.partials.card', ['movie' => $movie])
            @endforeach
        </div>

        @include('partials.pager', ['paginator' => $movies])
    @endif
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="sparkle" :size="18" />
            <h3>Trending</h3>
        </div>

        @forelse ($trending as $movie)
            <a class="mv-mini" href="{{ route('movies.show', $movie) }}">
                <span class="mv-mini-rank">{{ $loop->iteration }}</span>
                <span class="mv-mini-body">
                    <span class="mv-mini-title">{{ $movie->title }}</span>
                    <span class="mv-sub">{{ $movie->viewsLabel() }}</span>
                </span>
            </a>
        @empty
            <p class="small muted">Nothing has been watched yet.</p>
        @endforelse
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="film" :size="18" />
            <h3>Library</h3>
        </div>

        <dl class="kv-list">
            <div><dt>Movies</dt><dd>{{ number_format($totalMovies) }}</dd></div>
            <div><dt>Hours to watch</dt><dd>{{ number_format($totalHours) }}</dd></div>
            @auth
                <div><dt>Your uploads</dt><dd>{{ number_format($myCount) }}</dd></div>
            @endauth
        </dl>

        @auth
            <a class="btn btn-sm btn-block" href="{{ route('movies.create') }}" style="margin-top:.85rem">
                <x-icon name="upload" :size="15" /> Upload a movie
            </a>
        @else
            <a class="btn btn-ghost btn-sm btn-block" href="{{ route('login') }}" data-auth-open="login" style="margin-top:.85rem">Sign in to upload</a>
        @endauth
    </section>
@endsection
