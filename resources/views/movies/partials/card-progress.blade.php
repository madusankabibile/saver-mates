{{-- Movie card with progress bar for continue watching section --}}
@php
    $progress = App\Models\MovieWatchProgress::where('user_id', auth()->id())
        ->where('movie_id', $movie->id)
        ->first();
    $progressPercent = $progress?->progress_percent ?? 0;
@endphp

<a class="mv-card mv-card-progress" href="{{ route('movies.show', $movie) }}">
    <span class="mv-thumb" style="--hue: {{ $movie->posterHue() }}">
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

        {{-- Progress bar showing how much has been watched --}}
        <span class="mv-progress-bar" style="--progress: {{ $progressPercent }}%">
            <i style="width: {{ $progressPercent }}%"></i>
        </span>

        {{-- Resume badge --}}
        <span class="mv-resume-badge">
            <x-icon name="play" :size="14" /> Resume
        </span>
    </span>

    <span class="mv-meta">
        <x-avatar :user="$movie->user" :size="34" />
        <span class="mv-text">
            <span class="mv-title">{{ $movie->title }}</span>
            <span class="mv-sub">{{ $movie->user->name }}</span>
            <span class="mv-sub">{{ round($progressPercent) }}% watched &middot; {{ $movie->viewsLabel() }}</span>
        </span>
    </span>
</a>
