@extends('layouts.admin')

@section('title', $label)
@section('crumb')Content /@endsection

@php
    use App\Models\DriveFile;
    use Illuminate\Support\Str;

    $tabs = [
        'posts' => 'Posts', 'comments' => 'Post comments', 'movies' => 'Movies',
        'movie-comments' => 'Movie comments', 'songs' => 'Songs', 'files' => 'Drive files',
    ];
    $sorts = ['newest' => 'Newest first', 'oldest' => 'Oldest first'];
    if (in_array($type, ['posts', 'movies', 'songs'])) $sorts['popular'] = 'Most popular';
    if (in_array($type, ['movies', 'songs', 'files'])) $sorts['largest'] = 'Largest first';
    $noun = ['posts' => 'post', 'comments' => 'comment', 'movie-comments' => 'comment', 'movies' => 'movie', 'songs' => 'song', 'files' => 'file'][$type];
@endphp

@section('content')
    <nav class="adm-tabs" aria-label="Content type">
        @foreach ($tabs as $key => $text)
            <a class="{{ $type === $key ? 'is-active' : '' }}"
               href="{{ route('admin.content.index', array_filter(['type' => $key, 'user' => request('user')])) }}">
                {{ $text }}<span>{{ number_format($totals[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="adm-card">
        <div class="adm-toolbar">
            <form method="GET" action="{{ route('admin.content.index', $type) }}" role="search">
                @if (request('user')) <input type="hidden" name="user" value="{{ request('user') }}"> @endif
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search {{ Str::lower($label) }} or member" aria-label="Search">
                @if ($type === 'files')
                    <select name="visibility" aria-label="Visibility" onchange="this.form.submit()">
                        <option value="">Any visibility</option>
                        <option value="public" @selected(request('visibility') === 'public')>On the network drive</option>
                        <option value="private" @selected(request('visibility') === 'private')>Private</option>
                    </select>
                @endif
                <select name="sort" aria-label="Sort" onchange="this.form.submit()">
                    @foreach ($sorts as $k => $v)
                        <option value="{{ $k }}" @selected(request('sort', 'newest') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <button class="btn btn-ghost btn-sm" type="submit"><x-icon name="search" :size="15" /> Search</button>
            </form>

            @if ($filterUser)
                <span class="tag tag-admin">
                    By {{ $filterUser->name }}
                    <a href="{{ route('admin.content.index', $type) }}" aria-label="Clear member filter" style="color:inherit">&#10005;</a>
                </span>
            @endif
            <span class="muted small">{{ number_format($items->total()) }} {{ Str::plural($noun, $items->total()) }}</span>
        </div>

        <form method="POST" action="{{ route('admin.content.bulk', $type) }}" data-bulk>
            @csrf

            <div class="table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th class="check"><input type="checkbox" data-bulk-all aria-label="Select all on this page"></th>
                            @switch($type)
                                @case('posts') <th>Post</th><th>Kind</th><th class="num">Reactions</th><th class="num">Comments</th> @break
                                @case('comments') <th>Comment</th><th>On post</th> @break
                                @case('movie-comments') <th>Comment</th><th>On movie</th> @break
                                @case('movies') <th>Movie</th><th>Category</th><th class="num">Views</th><th class="num">Size</th> @break
                                @case('songs') <th>Song</th><th>Artist</th><th class="num">Plays</th><th class="num">Size</th> @break
                                @case('files') <th>File</th><th>Visibility</th><th class="num">Size</th> @break
                            @endswitch
                            <th>By</th>
                            <th>Added</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td class="check"><input type="checkbox" name="ids[]" value="{{ $item->id }}" aria-label="Select"></td>

                                @switch($type)
                                    @case('posts')
                                        <td><a class="cell-clip wrap" href="{{ route('posts.show', $item) }}" target="_blank">{{ Str::limit($item->displayTitle(), 140) }}</a></td>
                                        <td><span class="tag">{{ ucfirst($item->type) }}</span></td>
                                        <td class="num">{{ $item->reactions_count }}</td>
                                        <td class="num">{{ $item->comments_count }}</td>
                                        @break
                                    @case('comments')
                                        <td><div class="cell-clip wrap">{{ $item->body }}</div></td>
                                        <td>@if ($item->post)<a class="cell-clip" style="display:block;max-width:220px" href="{{ route('posts.show', $item->post) }}" target="_blank">{{ $item->post->displayTitle() }}</a>@endif</td>
                                        @break
                                    @case('movie-comments')
                                        <td><div class="cell-clip wrap">{{ $item->body }}</div></td>
                                        <td>@if ($item->movie)<a class="cell-clip" style="display:block;max-width:220px" href="{{ route('movies.show', $item->movie) }}#comments" target="_blank">{{ $item->movie->title }}</a>@endif</td>
                                        @break
                                    @case('movies')
                                        <td>
                                            <a class="cell-user" href="{{ route('movies.show', $item) }}" target="_blank">
                                                @if ($item->thumbnailUrl()) <img class="thumb" src="{{ $item->thumbnailUrl() }}" alt="" loading="lazy"> @endif
                                                <span class="cu-name cell-clip">{{ $item->title }}</span>
                                            </a>
                                        </td>
                                        <td>{{ $item->category ?: '—' }}</td>
                                        <td class="num">{{ number_format($item->views) }}</td>
                                        <td class="num">{{ DriveFile::humanSize($item->video_size) }}</td>
                                        @break
                                    @case('songs')
                                        <td>
                                            <span class="cell-user">
                                                @if ($item->coverUrl()) <img class="thumb square" src="{{ $item->coverUrl() }}" alt="" loading="lazy"> @endif
                                                <span class="cu-name cell-clip">{{ $item->title }}</span>
                                            </span>
                                        </td>
                                        <td>{{ $item->artistName() }}</td>
                                        <td class="num">{{ number_format($item->plays) }}</td>
                                        <td class="num">{{ DriveFile::humanSize($item->size) }}</td>
                                        @break
                                    @case('files')
                                        <td>
                                            <a class="cell-clip" style="display:block" href="{{ route('drive.files.preview', $item) }}"
                                               data-lightbox="{{ route('drive.files.preview', $item) }}" data-mime="{{ $item->mime }}">{{ $item->name }}</a>
                                        </td>
                                        <td>
                                            @if ($item->is_public)
                                                <span class="tag tag-ok"><span class="dot"></span> Public</span>
                                            @else
                                                <span class="tag">Private</span>
                                            @endif
                                        </td>
                                        <td class="num">{{ $item->readableSize() }}</td>
                                        @break
                                @endswitch

                                <td class="nowrap">
                                    @if ($item->user)
                                        <a href="{{ route('admin.content.index', ['type' => $type, 'user' => $item->user_id]) }}" title="Show only {{ $item->user->name }}'s {{ Str::plural($noun) }}">{{ $item->user->name }}</a>
                                    @endif
                                </td>
                                <td class="nowrap small muted" title="{{ $item->created_at }}">{{ $item->created_at?->diffForHumans() }}</td>
                                <td>
                                    <div class="row-actions">
                                        @if ($type === 'files')
                                            <button class="mini-btn" type="submit" form="vis-{{ $item->id }}"
                                                    title="{{ $item->is_public ? 'Make private' : 'Publish to the network drive' }}">
                                                <x-icon :name="$item->is_public ? 'lock' : 'globe'" :size="15" />
                                            </button>
                                        @endif
                                        <button class="mini-btn" type="button" title="Delete"
                                                data-confirm="Delete this {{ $noun }}? This cannot be undone."
                                                data-action="{{ route('admin.content.destroy', ['type' => $type, 'id' => $item->id]) }}">
                                            <x-icon name="trash" :size="15" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="empty"><h3>Nothing here</h3><p>No {{ Str::plural($noun) }} match this view.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bulk-bar" data-bulk-bar hidden>
                <span class="bb-count" data-bulk-count></span>
                <button class="btn btn-ghost btn-sm" type="button" data-bulk-clear>Clear</button>
                <button class="btn btn-coral btn-sm" type="button" data-bulk-action="delete"
                        data-bulk-confirm="Permanently delete {n} {{ Str::plural($noun) }}? This cannot be undone."
                        data-bulk-confirm-title="Delete {{ Str::plural($noun) }}">Delete selected</button>
            </div>
        </form>

        {{-- Visibility toggles live outside the bulk form (forms cannot nest). --}}
        @if ($type === 'files')
            @foreach ($items as $item)
                <form id="vis-{{ $item->id }}" method="POST" action="{{ route('admin.content.files.visibility', $item) }}" hidden>@csrf</form>
            @endforeach
        @endif

        @include('partials.pager', ['paginator' => $items])
    </section>
@endsection
