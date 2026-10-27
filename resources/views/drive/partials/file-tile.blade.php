@php
    $badge = $file->badge();
    $owned = auth()->check() && auth()->id() === $file->user_id;
@endphp

<article class="file-tile">
    <div class="file-thumb">
        @if ($file->isImage())
            <img src="{{ $file->url() }}" alt="{{ $file->name }}" loading="lazy">
        @else
            <span class="big-glyph" style="background:{{ $badge['color'] }}">{{ $badge['glyph'] }}</span>
        @endif

        <span class="thumb-badge">
            @if ($file->is_public)
                <span class="badge-public"><x-icon name="globe" :size="12" /> Public</span>
            @else
                <span class="badge-private"><x-icon name="lock" :size="12" /> Private</span>
            @endif
        </span>
    </div>

    <div class="file-meta">
        @if ($file->isPreviewable())
            <a class="fm-name" href="{{ route('drive.files.preview', $file) }}"
               data-lightbox="{{ route('drive.files.preview', $file) }}" data-mime="{{ $file->mime }}"
               title="{{ $file->name }}">{{ $file->name }}</a>
        @else
            <a class="fm-name" href="{{ route('drive.files.download', $file) }}" title="{{ $file->name }}">{{ $file->name }}</a>
        @endif

        <div class="fm-sub">
            {{ $file->readableSize() }}
            @if (! $owned)
                &middot; <a href="{{ route('profile.show', $file->user) }}">{{ $file->user->name }}</a>
            @else
                &middot; {{ $file->created_at->diffForHumans(null, true) }} ago
            @endif
        </div>
    </div>

    <div class="file-foot">
        @if ($file->isPreviewable())
            <a class="mini-btn" href="{{ route('drive.files.preview', $file) }}"
               data-lightbox="{{ route('drive.files.preview', $file) }}" data-mime="{{ $file->mime }}"
               title="Preview">
                <x-icon name="eye" :size="15" />
            </a>
        @endif

        <a class="mini-btn" href="{{ route('drive.files.download', $file) }}" title="Download">
            <x-icon name="download" :size="15" />
        </a>

        @if ($owned)
            <button class="mini-btn" type="button" title="Rename"
                    data-rename="{{ route('drive.files.rename', $file) }}"
                    data-rename-value="{{ $file->name }}"
                    data-rename-title="Rename file">
                <x-icon name="pencil" :size="15" />
            </button>

            <span class="spacer"></span>

            <form method="POST" action="{{ route('drive.files.visibility', $file) }}" style="display:inline">
                @csrf
                <button class="privacy-toggle {{ $file->is_public ? 'is-public' : '' }}" type="submit"
                        title="{{ $file->is_public ? 'Make private' : 'Publish to the network drive' }}">
                    <span class="pt-track"></span>
                    {{ $file->is_public ? 'Public' : 'Private' }}
                </button>
            </form>

            <form method="POST" action="{{ route('drive.files.destroy', $file) }}" style="display:inline">
                @csrf
                @method('DELETE')
                <button class="mini-btn is-danger" type="button" title="Delete"
                        data-confirm="&ldquo;{{ $file->name }}&rdquo; will be deleted permanently."
                        data-confirm-title="Delete this file?" data-confirm-label="Delete file">
                    <x-icon name="trash" :size="15" />
                </button>
            </form>
        @else
            <span class="spacer"></span>
            <span class="tiny muted">{{ $file->extension() }}</span>
        @endif
    </div>
</article>
