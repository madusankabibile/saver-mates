@extends('layouts.app')

@section('title', 'Network Drive')

@section('content')
    <div class="page-head">
        <div>
            <h1>Network Drive</h1>
            <p>Files and folders colleagues have published. Open to everyone on the network.</p>
        </div>
    </div>

    <div class="filter-bar">
        <form class="search-form" method="GET" action="{{ route('drive.public') }}" role="search">
            <span class="si"><x-icon name="search" :size="17" /></span>
            <input type="search" name="q" value="{{ $term }}"
                   placeholder="Search every shared file and folder" aria-label="Search the network drive">
        </form>

        @if ($term)
            <a class="btn btn-ghost btn-sm" href="{{ route('drive.public') }}">
                <x-icon name="close" :size="15" /> Clear search
            </a>
        @endif
    </div>

    @if (! $term)
        <nav class="drive-crumbs" aria-label="Folder path">
            <a href="{{ route('drive.public') }}">
                <x-icon name="globe" :size="15" /> Network Drive
            </a>

            @foreach ($breadcrumbs as $crumb)
                <span class="sep">/</span>
                @if ($loop->last)
                    <span class="here">{{ $crumb->name }}</span>
                @else
                    <a href="{{ route('drive.public', ['folder' => $crumb->id]) }}">{{ $crumb->name }}</a>
                @endif
            @endforeach
        </nav>

        @if ($folder)
            <p class="small muted" style="margin:-.5rem 0 1.25rem">
                Shared by <a href="{{ route('profile.show', $folder->user) }}">{{ $folder->user->name }}</a>
            </p>
        @endif
    @else
        <p class="small muted" style="margin:-.5rem 0 1rem">
            {{ $files->total() }} {{ \Illuminate\Support\Str::plural('file', $files->total()) }}
            and {{ $folders->count() }} {{ \Illuminate\Support\Str::plural('folder', $folders->count()) }}
            match &ldquo;{{ $term }}&rdquo;
        </p>
    @endif

    @if ($folders->isEmpty() && $files->isEmpty())
        <div class="card card-pad empty">
            <div class="em-icon"><x-icon name="globe" :size="34" /></div>
            <h3>{{ $term ? 'Nothing matches that search' : 'Nothing published yet' }}</h3>
            <p>
                @if ($term)
                    Try a shorter search term, or part of a file name.
                @else
                    When a colleague flips a file or folder to Public in their drive, it lands here for everyone.
                @endif
            </p>
            @auth
                @unless ($term)
                    <a class="btn btn-sm" href="{{ route('drive.index') }}">Publish something from my drive</a>
                @endunless
            @endauth
        </div>
    @else
        @if ($folders->isNotEmpty())
            <h2 style="font-size:1.05rem;margin-bottom:.75rem">Shared folders</h2>
            <div class="folder-row">
                @foreach ($folders as $pubFolder)
                    <a class="folder-tile" href="{{ route('drive.public', ['folder' => $pubFolder->id]) }}">
                        <span class="ft-icon"><x-icon name="folder" :size="19" /></span>
                        <span class="ft-body">
                            <span class="ft-name">{{ $pubFolder->name }}</span>
                            <span class="ft-meta">
                                {{ $pubFolder->files_count }} {{ \Illuminate\Support\Str::plural('file', $pubFolder->files_count) }}
                                &middot; {{ $pubFolder->user->name }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($files->isNotEmpty())
            <h2 style="font-size:1.05rem;margin-bottom:.75rem">Shared files</h2>
            <div class="file-grid">
                @foreach ($files as $file)
                    @include('drive.partials.file-tile', ['file' => $file])
                @endforeach
            </div>

            @include('partials.pager', ['paginator' => $files])
        @endif
    @endif
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="globe" :size="18" />
            <h3>Network drive</h3>
        </div>

        <dl class="kv-list">
            <div><dt>{{ \Illuminate\Support\Str::plural('Shared file', $totalPublicFiles) }}</dt><dd>{{ number_format($totalPublicFiles) }}</dd></div>
            <div><dt>Total size</dt><dd>{{ \App\Models\DriveFile::humanSize((int) $totalPublicSize) }}</dd></div>
            <div><dt>{{ \Illuminate\Support\Str::plural('Contributor', $contributors) }}</dt><dd>{{ number_format($contributors) }}</dd></div>
        </dl>

        @auth
            <a class="btn btn-sm btn-block" href="{{ route('drive.index') }}" style="margin-top:.85rem">
                <x-icon name="upload" :size="15" /> Publish from my drive
            </a>
        @else
            <a class="btn btn-sm btn-block" href="{{ route('login') }}" data-auth-open="login" style="margin-top:.85rem">
                Sign in to share files
            </a>
        @endauth
    </section>
@endsection
