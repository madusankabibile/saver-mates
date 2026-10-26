{{-- One software item in the browse grid --}}
<a class="sw-card" href="{{ route('software.show', $item) }}">
    <span class="sw-thumb" style="--hue: {{ $item->posterHue() }}">
        @if ($item->thumbnailUrl())
            <img src="{{ $item->thumbnailUrl() }}" alt="" loading="lazy">
        @else
            <span class="sw-fallback">
                <x-icon name="package" :size="30" />
                <span>{{ \Illuminate\Support\Str::limit($item->title, 36) }}</span>
            </span>
        @endif

        @if ($item->os_type)
            <span class="sw-badge sw-os">{{ $item->os_type }}</span>
        @endif
        <span class="sw-badge sw-size">{{ $item->sizeLabel() }}</span>
    </span>

    <span class="sw-meta">
        <x-avatar :user="$item->user" :size="34" />
        <span class="sw-text">
            <span class="sw-title">{{ $item->title }}@if($item->version) <span class="sw-version">v{{ $item->version }}</span>@endif</span>
            <span class="sw-sub">{{ $item->user->name }}</span>
            <span class="sw-sub">{{ $item->downloadsLabel() }} &middot; {{ $item->created_at->diffForHumans() }}</span>
        </span>
    </span>
</a>
