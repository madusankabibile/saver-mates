{{-- One movie in the browse grid. The red bar shows how far this browser got (from localStorage). --}}
<a class="mv-card" href="{{ route('movies.show', $movie) }}">
    <span class="mv-thumb" style="--hue: {{ $movie->posterHue() }}"
          @if (! $movie->thumbnail_path && auth()->id() === $movie->user_id)
              data-capture-src="{{ $movie->videoUrl() }}" data-capture-url="{{ route('movies.thumbnail', $movie) }}"
          @endif>
        @if ($movie->thumbnailUrl())
            <img src="{{ $movie->thumbnailUrl() }}" alt="" loading="lazy">
        @else
            <span class="mv-fallback">
                <x-icon name="film" :size="30" />
                <span>{{ \Illuminate\Support\Str::limit($movie->title, 40) }}</span>
            </span>
        @endif

        @if ($movie->durationLabel())
            <span class="mv-duration">{{ $movie->durationLabel() }}</span>
        @endif

        <span class="mv-progress" data-progress-for="{{ $movie->id }}" data-duration="{{ $movie->duration }}" hidden><i></i></span>
    </span>

    <span class="mv-meta">
        <x-avatar :user="$movie->user" :size="34" />
        <span class="mv-text">
            <span class="mv-title">{{ $movie->title }}</span>
            <span class="mv-sub">{{ $movie->user->name }}</span>
            <span class="mv-sub">{{ $movie->viewsLabel() }} &middot; {{ $movie->created_at->diffForHumans() }}</span>
        </span>
    </span>
</a>
