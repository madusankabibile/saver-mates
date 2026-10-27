@extends('layouts.app')

@section('title', $movie->title)
@section('main-class', 'main-panel-flush')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media.css') }}?v={{ filemtime(public_path('css/media.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/movies.js') }}?v={{ filemtime(public_path('js/movies.js')) }}" defer></script>
@endpush

@php
    $next = $related->first();
    $isOwner = auth()->id() === $movie->user_id;
@endphp

@section('content')
    <div class="watch" data-watch
         data-movie-id="{{ $movie->id }}"
         data-view-url="{{ route('movies.view', $movie) }}"
         @auth data-progress-url="{{ route('movies.progress.save', $movie) }}" @endauth
         @if ($next) data-next-url="{{ route('movies.show', $next) }}" data-next-title="{{ $next->title }}" @endif
         @if ($needsThumbnail) data-thumb-url="{{ route('movies.thumbnail', $movie) }}" @endif>

        <div class="mv-player" data-player-wrap>
            {{-- MKV is announced as WebM (a subset of Matroska) so Chrome and Edge try it
                 instead of refusing outright; H.264/AAC MKVs then play. --}}
            <video data-player controls preload="metadata" playsinline
                   @if ($movie->thumbnailUrl()) poster="{{ $movie->thumbnailUrl() }}" @endif>
                <source src="{{ $movie->videoUrl() }}" type="{{ $movie->video_mime === 'video/x-matroska' ? 'video/webm' : $movie->video_mime }}">
                @foreach ($movie->subtitles as $sub)
                    <track kind="subtitles" src="{{ $sub->url() }}" srclang="{{ $sub->lang }}" label="{{ $sub->label }}" data-sub-id="{{ $sub->id }}">
                @endforeach
            </video>

            <div class="mv-toast" data-player-toast hidden></div>

            {{-- Shown when the movie ends and autoplay is on. --}}
            <div class="mv-upnext" data-upnext hidden>
                <div class="mv-upnext-card">
                    <span class="small">Up next in <b data-upnext-count>8</b></span>
                    <strong data-upnext-title></strong>
                    <div class="mv-upnext-actions">
                        <button class="btn btn-ghost btn-sm" type="button" data-upnext-cancel>Cancel</button>
                        <button class="btn btn-sm" type="button" data-upnext-go><x-icon name="play" :size="14" /> Play now</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Player toolbar: subtitles, size, delay, speed, theater, autoplay. --}}
        <div class="mv-toolbar">
            <label class="mv-tool">
                <x-icon name="captions" :size="17" />
                <select data-sub-select aria-label="Subtitles">
                    <option value="">Subtitles off</option>
                    @foreach ($movie->subtitles as $sub)
                        <option value="{{ $loop->index }}">{{ $sub->label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="mv-tool" data-sub-tools @if ($movie->subtitles->isEmpty()) hidden @endif>
                <span class="tiny muted">Size</span>
                <div class="seg seg-sm">
                    <button type="button" data-sub-size="s">S</button>
                    <button type="button" data-sub-size="m" class="is-active">M</button>
                    <button type="button" data-sub-size="l">L</button>
                </div>
            </div>

            <div class="mv-tool" data-sub-tools @if ($movie->subtitles->isEmpty()) hidden @endif>
                <span class="tiny muted">Sync</span>
                <button class="mini-btn" type="button" data-sub-shift="-0.5" title="Subtitles earlier (Z)">&minus;</button>
                <span class="tiny mv-sync" data-sub-offset>0.0s</span>
                <button class="mini-btn" type="button" data-sub-shift="0.5" title="Subtitles later (X)">+</button>
            </div>

            <label class="mv-tool">
                <x-icon name="clock" :size="17" />
                <select data-speed aria-label="Playback speed">
                    @foreach ([0.5, 0.75, 1, 1.25, 1.5, 2] as $rate)
                        <option value="{{ $rate }}" @selected($rate == 1)>{{ $rate == 1 ? 'Normal' : $rate.'x' }}</option>
                    @endforeach
                </select>
            </label>

            <span class="spacer"></span>

            <button class="mv-tool mv-tool-btn" type="button" data-theater title="Theater mode (T)">
                <x-icon name="theater" :size="17" /> <span>Theater</span>
            </button>

            <label class="mv-tool mv-switch" title="Play the next movie automatically">
                <input type="checkbox" data-autoplay>
                <span class="mv-switch-track"></span>
                <span>Autoplay</span>
            </label>
        </div>

        <div class="watch-body">
            <h1 class="watch-title">{{ $movie->title }}</h1>

            <div class="watch-bar">
                <a class="watch-owner" href="{{ route('profile.show', $movie->user) }}">
                    <x-avatar :user="$movie->user" :size="42" :presence="true" />
                    <span>
                        <span class="strong">{{ $movie->user->name }}</span>
                        <span class="tiny muted" style="display:block">{{ $uploaderCount }} {{ \Illuminate\Support\Str::plural('movie', $uploaderCount) }}</span>
                    </span>
                </a>

                <span class="spacer"></span>

                @auth
                    <button class="pill-btn {{ $liked ? 'is-on' : '' }}" type="button"
                            data-like-url="{{ route('movies.like', $movie) }}" aria-pressed="{{ $liked ? 'true' : 'false' }}">
                        <span class="ic-off"><x-icon name="thumb" :size="17" /></span>
                        <span class="ic-on"><x-icon name="thumb-fill" :size="17" /></span>
                        <span data-like-count>{{ number_format($movie->likers_count) }}</span>
                    </button>
                @else
                    <a class="pill-btn" href="{{ route('login') }}" data-auth-open="login" title="Sign in to like">
                        <x-icon name="thumb" :size="17" /> {{ number_format($movie->likers_count) }}
                    </a>
                @endauth

                <button class="pill-btn" type="button" data-copy-link="{{ route('movies.show', $movie) }}">
                    <x-icon name="send" :size="16" /> <span>Share</span>
                </button>

                <a class="pill-btn" href="{{ $movie->videoUrl() }}" download>
                    <x-icon name="download" :size="16" /> Download
                </a>

                @if ($isOwner)
                    <a class="pill-btn" href="{{ route('movies.edit', $movie) }}">
                        <x-icon name="pencil" :size="16" /> Edit
                    </a>
                    <form method="POST" action="{{ route('movies.destroy', $movie) }}">
                        @csrf
                        @method('DELETE')
                        <button class="pill-btn is-danger" type="button"
                                data-confirm="The video, its thumbnail and subtitles are deleted for everyone."
                                data-confirm-title="Delete this movie?" data-confirm-label="Delete movie">
                            <x-icon name="trash" :size="16" />
                        </button>
                    </form>
                @endif
            </div>

            @unless ($movie->isBrowserFriendly())
                <div class="mv-warning">
                    <x-icon name="shield" :size="17" />
                    <span>
                        This video is a {{ strtoupper(pathinfo($movie->video_path, PATHINFO_EXTENSION)) }} file, which some browsers cannot play.
                        If it stays black, use Download and open it in a media player.
                    </span>
                </div>
            @endunless

            <div class="mv-desc" data-desc>
                <div class="mv-desc-meta">
                    <span data-view-count>{{ $movie->viewsLabel() }}</span>
                    <span>{{ $movie->created_at->format('j M Y') }}</span>
                    @if ($movie->durationLabel())<span>{{ $movie->durationLabel() }}</span>@endif
                    @if ($movie->category)
                        <a class="mchip mchip-sm" href="{{ route('movies.index', ['category' => $movie->category]) }}">{{ $movie->category }}</a>
                    @endif
                    @foreach ($movie->subtitles as $sub)
                        <span class="mchip mchip-sm mchip-plain"><x-icon name="captions" :size="13" /> {{ $sub->label }}</span>
                    @endforeach
                </div>

                @if ($movie->description)
                    <p class="mv-desc-text" data-desc-text>{{ $movie->description }}</p>
                    <button class="mv-desc-more" type="button" data-desc-more hidden>Show more</button>
                @else
                    <p class="mv-desc-text muted">No description.</p>
                @endif
            </div>

            <details class="mv-keys">
                <summary>Keyboard shortcuts</summary>
                <div class="mv-keys-grid">
                    <span><kbd>K</kbd> or <kbd>Space</kbd> Play / pause</span>
                    <span><kbd>J</kbd> / <kbd>L</kbd> Back / forward 10s</span>
                    <span><kbd>&larr;</kbd> / <kbd>&rarr;</kbd> Back / forward 5s</span>
                    <span><kbd>&uarr;</kbd> / <kbd>&darr;</kbd> Volume</span>
                    <span><kbd>F</kbd> Fullscreen</span>
                    <span><kbd>M</kbd> Mute</span>
                    <span><kbd>C</kbd> Subtitles on / off</span>
                    <span><kbd>Z</kbd> / <kbd>X</kbd> Subtitle sync</span>
                    <span><kbd>T</kbd> Theater mode</span>
                    <span><kbd>&lt;</kbd> / <kbd>&gt;</kbd> Speed</span>
                    <span><kbd>0</kbd>&ndash;<kbd>9</kbd> Jump to 0&ndash;90%</span>
                </div>
            </details>

            {{-- Comments. Posted and deleted in place so the movie keeps playing. --}}
            <section class="mv-comments" id="comments" data-movie-comments>
                <h2 class="mv-comments-title">
                    <span data-comment-count>{{ number_format($movie->all_comments_count) }}</span>
                    <span data-comment-word>{{ \Illuminate\Support\Str::plural('Comment', $movie->all_comments_count) }}</span>
                </h2>

                @auth
                    <form class="comment-form mv-comment-new" method="POST" action="{{ route('movie-comments.store', $movie) }}" data-movie-comment-form>
                        <x-avatar :user="auth()->user()" :size="36" />
                        <textarea name="body" rows="1" maxlength="2000" required placeholder="Add a comment&hellip;"></textarea>
                        <button class="chat-send" type="submit" aria-label="Post comment" style="width:38px;height:38px">
                            <x-icon name="send" :size="16" />
                        </button>
                    </form>
                    <p class="tiny muted mv-comment-hint">Enter to post, Shift+Enter for a new line.</p>
                @else
                    <p class="small muted">
                        <a href="{{ route('login') }}" data-auth-open="login">Sign in</a> to join the conversation.
                    </p>
                @endauth

                <div class="comments mv-comment-list" data-comment-list>
                    @foreach ($movie->comments as $comment)
                        @include('movies.partials.comment', ['comment' => $comment, 'movie' => $movie])
                    @endforeach
                </div>

                <p class="small muted" data-no-comments @if ($movie->comments->isNotEmpty()) hidden @endif>
                    No comments yet. Be the first to say what you thought.
                </p>
            </section>
        </div>
    </div>
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="film" :size="18" />
            <h3>Up next</h3>
        </div>

        <div class="mv-related">
            @forelse ($related as $item)
                <a class="mv-rel" href="{{ route('movies.show', $item) }}">
                    <span class="mv-thumb mv-thumb-sm" style="--hue: {{ $item->posterHue() }}">
                        @if ($item->thumbnailUrl())
                            <img src="{{ $item->thumbnailUrl() }}" alt="" loading="lazy">
                        @else
                            <span class="mv-fallback"><x-icon name="film" :size="20" /></span>
                        @endif
                        @if ($item->durationLabel())
                            <span class="mv-duration">{{ $item->durationLabel() }}</span>
                        @endif
                        <span class="mv-progress" data-progress-for="{{ $item->id }}" data-duration="{{ $item->duration }}" hidden><i></i></span>
                    </span>
                    <span class="mv-rel-body">
                        <span class="mv-rel-title">{{ $item->title }}</span>
                        <span class="mv-sub">{{ $item->user->name }}</span>
                        <span class="mv-sub">{{ $item->viewsLabel() }}</span>
                    </span>
                </a>
            @empty
                <p class="small muted">No other movies yet.</p>
            @endforelse
        </div>

        <a class="btn btn-ghost btn-sm btn-block" href="{{ route('movies.index') }}" style="margin-top:.75rem">
            Browse all movies
        </a>
    </section>
@endsection
