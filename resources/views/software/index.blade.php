@extends('layouts.app')

@section('title', 'Software')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media.css') }}?v={{ filemtime(public_path('css/media.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/software.js') }}?v={{ filemtime(public_path('js/software.js')) }}" defer></script>
@endpush

@section('content')
    <div class="page-head">
        <div>
            <h1>Software</h1>
            <p>Software shared by your colleagues. Anyone on the network can download.</p>
        </div>
        <div class="spacer"></div>
        @auth
            <a class="btn" href="{{ route('software.create') }}">
                <x-icon name="upload" :size="16" /> Upload software
            </a>
        @endauth
    </div>

    <div class="filter-bar">
        <form class="search-form" method="GET" action="{{ route('software.index') }}" role="search">
            <span class="si"><x-icon name="search" :size="17" /></span>
            <input type="search" name="q" value="{{ $term }}" placeholder="Search software" aria-label="Search software">
            @if ($osType)<input type="hidden" name="os_type" value="{{ $osType }}">@endif
            @if ($sort !== 'latest')<input type="hidden" name="sort" value="{{ $sort }}">@endif
        </form>

        <div class="seg">
            @foreach (['latest' => 'Latest', 'popular' => 'Most downloaded', 'liked' => 'Most liked'] as $key => $label)
                <a class="{{ $sort === $key ? 'is-active' : '' }}"
                   href="{{ route('software.index', array_filter(['q' => $term, 'os_type' => $osType, 'sort' => $key === 'latest' ? null : $key])) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <nav class="chip-scroll" aria-label="OS types">
        <a class="mchip {{ ! $osType ? 'is-active' : '' }}"
           href="{{ route('software.index', array_filter(['q' => $term, 'sort' => $sort === 'latest' ? null : $sort])) }}">All</a>
        @foreach ($osTypes as $os)
            <a class="mchip {{ $osType === $os ? 'is-active' : '' }}"
               href="{{ route('software.index', array_filter(['q' => $term, 'os_type' => $os, 'sort' => $sort === 'latest' ? null : $sort])) }}">{{ $os }}</a>
        @endforeach
    </nav>

    @if ($software->isEmpty())
        <div class="empty" style="padding:3rem 1rem">
            <div class="em-icon"><x-icon name="package" :size="34" /></div>
            <h3>{{ $term || $osType ? 'No software matches' : 'No software yet' }}</h3>
            <p>
                @if ($term || $osType)
                    Try another search or OS filter.
                @else
                    Upload the first piece of software and it will appear here for everyone on the network.
                @endif
            </p>
            @auth
                <a class="btn btn-sm" href="{{ route('software.create') }}">Upload software</a>
            @endauth
        </div>
    @else
        <div class="sw-grid">
            @foreach ($software as $item)
                @include('software.partials.card', ['item' => $item])
            @endforeach
        </div>

        @include('partials.pager', ['paginator' => $software])
    @endif
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="sparkle" :size="18" />
            <h3>Trending</h3>
        </div>

        @forelse ($trending as $item)
            <a class="mv-mini" href="{{ route('software.show', $item) }}">
                <span class="mv-mini-rank">{{ $loop->iteration }}</span>
                <span class="mv-mini-body">
                    <span class="mv-mini-title">{{ $item->title }}</span>
                    <span class="mv-sub">{{ $item->downloadsLabel() }}</span>
                </span>
            </a>
        @empty
            <p class="small muted">Nothing has been downloaded yet.</p>
        @endforelse
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="package" :size="18" />
            <h3>Library</h3>
        </div>

        <dl class="kv-list">
            <div><dt>Total items</dt><dd>{{ number_format($totalSoftware) }}</dd></div>
            <div><dt>Total size</dt><dd>
                @php
                    $ts = $totalSize;
                    if ($ts >= 1073741824) echo round($ts / 1073741824, 1) . ' GB';
                    elseif ($ts >= 1048576) echo round($ts / 1048576, 1) . ' MB';
                    else echo round($ts / 1024, 1) . ' KB';
                @endphp
            </dd></div>
            @auth
                <div><dt>Your uploads</dt><dd>{{ number_format($myCount) }}</dd></div>
            @endauth
        </dl>

        @auth
            <a class="btn btn-sm btn-block" href="{{ route('software.create') }}" style="margin-top:.85rem">
                <x-icon name="upload" :size="15" /> Upload software
            </a>
        @else
            <a class="btn btn-ghost btn-sm btn-block" href="{{ route('login') }}" data-auth-open="login" style="margin-top:.85rem">Sign in to upload</a>
        @endauth
    </section>
@endsection
