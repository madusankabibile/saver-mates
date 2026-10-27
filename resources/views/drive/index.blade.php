@extends('layouts.app')

@section('title', $folder ? $folder->name : 'My Drive')

@section('content')
    <div class="page-head">
        <div>
            <h1>My Drive</h1>
            <p>Everything here is private until you publish it to the network drive.</p>
        </div>
    </div>

    {{-- Breadcrumb trail -------------------------------------------------- --}}
    <nav class="drive-crumbs" aria-label="Folder path">
        <a href="{{ route('drive.index') }}">
            <x-icon name="cloud" :size="15" /> My Drive
        </a>

        @foreach ($breadcrumbs as $crumb)
            <span class="sep">/</span>
            @if ($loop->last)
                <span class="here">{{ $crumb->name }}</span>
                @if ($crumb->is_public)
                    <span class="badge-public"><x-icon name="globe" :size="12" /> Public</span>
                @endif
            @else
                <a href="{{ route('drive.folder', $crumb) }}">{{ $crumb->name }}</a>
            @endif
        @endforeach
    </nav>

    {{-- Toolbar ----------------------------------------------------------- --}}
    <div class="drive-toolbar">
        <button class="btn" type="button"
                data-new-folder="{{ route('drive.folders.store') }}"
                data-parent="{{ $folder?->id }}">
            <x-icon name="plus" :size="16" /> New folder
        </button>

        {{-- Opens the single upload form further down the page. --}}
        <label class="btn btn-ghost" for="drive-upload" style="cursor:pointer">
            <x-icon name="upload" :size="16" /> Upload files
        </label>

        <div class="spacer"></div>

        @if ($folder)
            <form method="POST" action="{{ route('drive.folders.visibility', $folder) }}" style="display:inline">
                @csrf
                <button class="privacy-toggle {{ $folder->is_public ? 'is-public' : '' }}" type="submit"
                        title="{{ $folder->is_public ? 'Make this folder private' : 'Publish this folder to the network drive' }}">
                    <span class="pt-track"></span>
                    {{ $folder->is_public ? 'Public folder' : 'Private folder' }}
                </button>
            </form>

            <button class="mini-btn" type="button"
                    data-rename="{{ route('drive.folders.rename', $folder) }}"
                    data-rename-value="{{ $folder->name }}"
                    data-rename-title="Rename folder" title="Rename this folder">
                <x-icon name="pencil" :size="15" />
            </button>

            <form method="POST" action="{{ route('drive.folders.destroy', $folder) }}" style="display:inline">
                @csrf
                @method('DELETE')
                <button class="mini-btn is-danger" type="button" title="Delete this folder"
                        data-confirm="&ldquo;{{ $folder->name }}&rdquo; and everything inside it will be deleted permanently."
                        data-confirm-title="Delete this folder?" data-confirm-label="Delete folder">
                    <x-icon name="trash" :size="15" />
                </button>
            </form>
        @endif
    </div>

    {{-- The one upload form on the page: the toolbar label and this drop zone
         both feed it, and app.js submits as soon as files are chosen. ------- --}}
    <form method="POST" action="{{ route('drive.files.store') }}" enctype="multipart/form-data"
          style="margin-bottom:1.5rem" data-upload-form>
        @csrf
        <input type="hidden" name="folder_id" value="{{ $folder?->id }}">
        <input type="file" name="files[]" id="drive-upload" multiple hidden>
        <div class="dropzone" data-dropzone tabindex="0" role="button"
             aria-label="Upload files to {{ $folder->name ?? 'My Drive' }}">
            <div class="dz-icon"><x-icon name="upload" :size="26" /></div>
            <div class="dz-main">Drop files here to upload</div>
            <div class="dz-sub">
                Or click to pick from your computer. Up to 10 files, 50&nbsp;MB each.
                @if ($folder?->is_public)
                    Files added here are public straight away.
                @else
                    They stay private until you publish them.
                @endif
            </div>
        </div>
    </form>

    @if ($folders->isEmpty() && $files->isEmpty())
        <div class="card card-pad empty">
            <div class="em-icon"><x-icon name="cloud" :size="34" /></div>
            <h3>{{ $folder ? 'This folder is empty' : 'Your drive is empty' }}</h3>
            <p>Upload a document or create a folder to get organised. Nothing is shared until you say so.</p>
        </div>
    @else
        @if ($folders->isNotEmpty())
            <h2 style="font-size:1.05rem;margin-bottom:.75rem">Folders</h2>
            <div class="folder-row">
                @foreach ($folders as $child)
                    <a class="folder-tile" href="{{ route('drive.folder', $child) }}">
                        <span class="ft-icon"><x-icon name="folder" :size="19" /></span>
                        <span class="ft-body">
                            <span class="ft-name">{{ $child->name }}</span>
                            <span class="ft-meta">
                                {{ $child->files_count }} {{ \Illuminate\Support\Str::plural('file', $child->files_count) }}
                                @if ($child->children_count)
                                    , {{ $child->children_count }} {{ \Illuminate\Support\Str::plural('folder', $child->children_count) }}
                                @endif
                            </span>
                        </span>
                        @if ($child->is_public)
                            <span class="badge-public"><x-icon name="globe" :size="12" /></span>
                        @else
                            <span class="badge-private"><x-icon name="lock" :size="12" /></span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

        @if ($files->isNotEmpty())
            <h2 style="font-size:1.05rem;margin-bottom:.75rem">
                Files
                <span class="muted" style="font-weight:400;font-size:.88rem">
                    ({{ $totals['public'] }} of {{ $totals['files'] }} published to the network)
                </span>
            </h2>

            <div class="file-grid">
                @foreach ($files as $file)
                    @include('drive.partials.file-tile', ['file' => $file])
                @endforeach
            </div>
        @endif
    @endif
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="cloud" :size="18" />
            <h3>Storage</h3>
        </div>

        <div class="usage-meter">
            <span class="bar"><i style="width:{{ min(100, $usage / (2 * 1024 * 1024 * 1024) * 100) }}%"></i></span>
            <span>{{ \App\Models\DriveFile::humanSize((int) $usage) }} stored</span>
        </div>

        <dl class="kv-list">
            <div><dt>Files</dt><dd>{{ number_format($totals['files']) }}</dd></div>
            <div><dt>Folders</dt><dd>{{ number_format($totals['folders']) }}</dd></div>
            <div><dt>Published</dt><dd>{{ number_format($totals['public']) }}</dd></div>
        </dl>
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="globe" :size="18" />
            <h3>Sharing</h3>
        </div>
        <p class="small muted">
            Files stay private until you publish them. Published files and folders appear on the
            network drive and on your profile.
        </p>
        <a class="btn btn-ghost btn-sm btn-block" href="{{ route('drive.public') }}">
            <x-icon name="globe" :size="15" /> Open the network drive
        </a>
    </section>
@endsection
