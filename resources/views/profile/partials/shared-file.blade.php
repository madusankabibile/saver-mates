@php
    /** @var \App\Models\DriveFile $file */
    $badge = $file->badge();
@endphp

<div class="file-row" data-tree-file data-name="{{ $file->name }}">
    <span class="file-glyph" style="background:{{ $badge['color'] }}">{{ $badge['glyph'] }}</span>

    <span class="fr-body">
        <span class="fr-name" title="{{ $file->name }}">{{ $file->name }}</span>
        <span class="fr-meta">
            {{ $file->readableSize() }} &middot; shared {{ $file->updated_at->diffForHumans() }}
        </span>
    </span>

    <span class="fr-actions">
        @if ($file->isPreviewable())
            <button class="mini-btn" type="button" title="Preview {{ $file->name }}"
                    data-lightbox="{{ route('drive.files.preview', $file) }}"
                    data-mime="{{ $file->mime }}">
                <x-icon name="eye" :size="15" />
            </button>
        @endif

        <a class="btn btn-ghost btn-sm" href="{{ route('drive.files.download', $file) }}">
            <x-icon name="download" :size="15" /> Download
        </a>
    </span>
</div>
