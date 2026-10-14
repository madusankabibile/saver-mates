@php
    /** @var \App\Models\DriveFolder $folder */
    $depth = $depth ?? 0;
    $childFolders = $folder->children;
    $childFiles = $folder->files;
    $directSize = $childFiles->sum('size');
@endphp

<div class="tree-node" data-tree-folder data-name="{{ $folder->name }}">
    <button class="tree-head" type="button" data-tree-toggle aria-expanded="{{ $depth === 0 ? 'true' : 'false' }}">
        <span class="tree-caret" aria-hidden="true"><x-icon name="next" :size="14" /></span>
        <span class="tree-icon"><x-icon name="folder" :size="18" /></span>

        <span class="tree-label">
            <span class="tree-name">{{ $folder->name }}</span>
            <span class="tree-meta">
                {{ $childFiles->count() }} {{ \Illuminate\Support\Str::plural('file', $childFiles->count()) }}
                @if ($childFolders->isNotEmpty())
                    &middot; {{ $childFolders->count() }} {{ \Illuminate\Support\Str::plural('folder', $childFolders->count()) }}
                @endif
                @if ($directSize)
                    &middot; {{ \App\Models\DriveFile::humanSize((int) $directSize) }}
                @endif
            </span>
        </span>

        <span class="badge-public"><x-icon name="globe" :size="12" /> Public</span>
    </button>

    <div class="tree-body" @if ($depth > 0) hidden @endif>
        @if ($childFolders->isEmpty() && $childFiles->isEmpty())
            <p class="small muted" style="padding:.5rem .4rem">This folder is empty.</p>
        @endif

        @foreach ($childFolders as $child)
            @include('profile.partials.shared-folder', ['folder' => $child, 'depth' => $depth + 1])
        @endforeach

        @foreach ($childFiles as $file)
            @include('profile.partials.shared-file', ['file' => $file])
        @endforeach
    </div>
</div>
