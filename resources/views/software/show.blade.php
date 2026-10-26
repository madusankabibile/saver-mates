@extends('layouts.app')

@section('title', $software->title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media.css') }}?v={{ filemtime(public_path('css/media.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/software.js') }}?v={{ filemtime(public_path('js/software.js')) }}" defer></script>
@endpush

@section('content')
    <div class="sw-hero" style="--hue: {{ $software->posterHue() }}" data-sw-show>

        {{-- Cover image / fallback gradient --}}
        <div class="sw-cover">
            @if ($software->thumbnailUrl())
                <img src="{{ $software->thumbnailUrl() }}" alt="{{ $software->title }}">
            @else
                <div class="sw-cover-fallback">
                    <x-icon name="package" :size="40" />
                    <span>{{ \Illuminate\Support\Str::limit($software->title, 30) }}</span>
                </div>
            @endif
        </div>

        <div class="sw-info">
            <h1 class="sw-info-title">{{ $software->title }}</h1>

            <div class="sw-chips">
                @if ($software->version)
                    <span class="sw-chip">
                        <x-icon name="sparkle" :size="13" /> v{{ $software->version }}
                    </span>
                @endif
                @if ($software->os_type)
                    <span class="sw-chip is-os">
                        <x-icon name="globe" :size="13" /> {{ $software->os_type }}
                    </span>
                @endif
                @if ($software->category)
                    <a class="sw-chip"
                       href="{{ route('software.index', ['category_filter' => $software->category]) }}">{{ $software->category }}</a>
                @endif
                <span class="sw-chip">
                    <x-icon name="download" :size="13" /> {{ $software->downloadsLabel() }}
                </span>
                <span class="sw-chip">
                    <x-icon name="eye" :size="13" /> {{ $software->viewsLabel() }}
                </span>
            </div>

            <div class="sw-actions">
                {{-- Download button --}}
                <form method="POST" action="{{ route('software.download', $software) }}" style="display:inline">
                    @csrf
                    <button class="btn sw-dl-btn" type="submit" data-sw-download>
                        <x-icon name="download" :size="18" /> Download
                        <span class="tiny" style="opacity:.75;margin-left:.2rem">{{ $software->sizeLabel() }}</span>
                    </button>
                </form>

                {{-- Like button --}}
                @auth
                    <button class="pill-btn {{ $liked ? 'is-on' : '' }}" type="button"
                            data-sw-like-url="{{ route('software.like', $software) }}"
                            aria-pressed="{{ $liked ? 'true' : 'false' }}">
                        <span class="ic-off"><x-icon name="thumb" :size="17" /></span>
                        <span class="ic-on"><x-icon name="thumb-fill" :size="17" /></span>
                        <span data-sw-like-count>{{ number_format($software->likers_count) }}</span>
                    </button>
                @else
                    <a class="pill-btn" href="{{ route('login') }}" data-auth-open="login" title="Sign in to like">
                        <x-icon name="thumb" :size="17" /> {{ number_format($software->likers_count) }}
                    </a>
                @endauth

                {{-- Share button --}}
                <button class="pill-btn" type="button" data-sw-share="{{ route('software.show', $software) }}">
                    <x-icon name="send" :size="16" /> Share
                </button>

                @if ($isOwner)
                    <a class="pill-btn" href="{{ route('software.edit', $software) }}">
                        <x-icon name="pencil" :size="16" /> Edit
                    </a>
                    <form method="POST" action="{{ route('software.destroy', $software) }}" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button class="pill-btn is-danger" type="button"
                                data-confirm="The software file and thumbnail are permanently deleted for everyone."
                                data-confirm-title="Delete this software?" data-confirm-label="Delete software">
                            <x-icon name="trash" :size="16" />
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if ($software->description)
        <div class="sw-desc">
            <h3>About</h3>
            <p>{{ $software->description }}</p>
        </div>
    @endif

    <div class="sw-file-info">
        <dl>
            <dt>File name</dt><dd>{{ $software->file_name }}</dd>
            <dt>File size</dt><dd>{{ $software->sizeLabel() }}</dd>
            @if ($software->file_mime)
                <dt>File type</dt><dd>{{ $software->file_mime }}</dd>
            @endif
            @if ($software->version)
                <dt>Version</dt><dd>{{ $software->version }}</dd>
            @endif
            @if ($software->os_type)
                <dt>Platform</dt><dd>{{ $software->os_type }}</dd>
            @endif
            @if ($software->category)
                <dt>Category</dt><dd>{{ $software->category }}</dd>
            @endif
            <dt>Uploaded by</dt>
            <dd>
                <a href="{{ route('profile.show', $software->user) }}">{{ $software->user->name }}</a>
            </dd>
            <dt>Uploaded</dt><dd>{{ $software->created_at->format('j M Y') }}</dd>
            <dt>Downloads</dt><dd>{{ number_format($software->downloads) }}</dd>
        </dl>
    </div>
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="people" :size="18" />
            <h3>Uploader</h3>
        </div>
        <a class="person-row" href="{{ route('profile.show', $software->user) }}" style="padding:.4rem 0">
            <x-avatar :user="$software->user" :size="42" />
            <span class="pr-body">
                <span class="pr-name">{{ $software->user->name }}</span>
                <span class="pr-meta">{{ $uploaderCount }} {{ \Illuminate\Support\Str::plural('upload', $uploaderCount) }}</span>
            </span>
        </a>
    </section>

    @if ($similar->isNotEmpty())
        <section class="panel">
            <div class="panel-head">
                <x-icon name="package" :size="18" />
                <h3>Similar software</h3>
            </div>

            <div class="mv-related">
                @foreach ($similar as $item)
                    <a class="mv-rel" href="{{ route('software.show', $item) }}">
                        <span class="sw-thumb" style="--hue: {{ $item->posterHue() }}; width:52px; min-width:52px; aspect-ratio:1/1; border-radius: var(--r-md);">
                            @if ($item->thumbnailUrl())
                                <img src="{{ $item->thumbnailUrl() }}" alt="" loading="lazy">
                            @else
                                <span class="sw-fallback" style="font-size:.65rem">
                                    <x-icon name="package" :size="18" />
                                </span>
                            @endif
                        </span>
                        <span class="mv-rel-body">
                            <span class="mv-rel-title">{{ $item->title }}</span>
                            <span class="mv-sub">{{ $item->user->name }}</span>
                            <span class="mv-sub">{{ $item->downloadsLabel() }}</span>
                        </span>
                    </a>
                @endforeach
            </div>

            <a class="btn btn-ghost btn-sm btn-block" href="{{ route('software.index') }}" style="margin-top:.75rem">
                Browse all software
            </a>
        </section>
    @else
        <section class="panel">
            <div class="panel-head">
                <x-icon name="package" :size="18" />
                <h3>More software</h3>
            </div>
            <a class="btn btn-ghost btn-sm btn-block" href="{{ route('software.index') }}">Browse all software</a>
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.querySelector('[data-sw-show]');
            if (el && window.initSoftwareShow) initSoftwareShow(el);
        });
    </script>
@endpush
