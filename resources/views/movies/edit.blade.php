@extends('layouts.app')

@section('title', 'Edit '.$movie->title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media.css') }}?v={{ filemtime(public_path('css/media.css')) }}">
@endpush

@section('content')
    <div class="page-head">
        <div>
            <h1>Edit movie</h1>
            <p>{{ $movie->title }}</p>
        </div>
        <div class="spacer"></div>
        <a class="btn btn-ghost" href="{{ route('movies.show', $movie) }}">
            <x-icon name="back" :size="16" /> Back to the movie
        </a>
    </div>

    <form method="POST" action="{{ route('movies.update', $movie) }}" enctype="multipart/form-data" class="mv-upload-grid">
        @csrf

        <div>
            <div class="field">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="{{ old('title', $movie->title) }}" maxlength="150" required
                       class="{{ $errors->has('title') ? 'input-error' : '' }}">
                @error('title') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="6" maxlength="5000">{{ old('description', $movie->description) }}</textarea>
            </div>

            <div class="field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">No category</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}" @selected(old('category', $movie->category) === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <span class="label">Subtitles</span>
                @forelse ($movie->subtitles as $sub)
                    <label class="checkline mv-sub-existing">
                        <input type="checkbox" name="remove_subtitles[]" value="{{ $sub->id }}">
                        <x-icon name="captions" :size="15" /> {{ $sub->label }}
                        <span class="tiny muted">tick to remove</span>
                    </label>
                @empty
                    <p class="small muted">No subtitles yet.</p>
                @endforelse

                <div class="mv-sub-add">
                    @for ($i = 0; $i < 3; $i++)
                        <div class="mv-sub-row">
                            <input type="file" name="subtitles[{{ $i }}]" accept=".srt,.vtt">
                            <select name="subtitle_langs[{{ $i }}]" aria-label="Language">
                                <option value="">Guess from file name</option>
                                @foreach ($languages as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>
                @error('subtitles') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <button class="btn" type="submit">Save changes</button>
        </div>

        <div>
            <span class="label">Thumbnail</span>
            <div class="mv-thumb-preview" style="--hue: {{ $movie->posterHue() }}">
                @if ($movie->thumbnailUrl())
                    <img src="{{ $movie->thumbnailUrl() }}" alt="Current thumbnail" id="thumb-preview">
                @else
                    <span class="small muted" id="thumb-preview">No thumbnail yet. One is captured from the video when you open it.</span>
                @endif
            </div>
            <div class="field" style="margin-top:.6rem">
                <label for="thumbnail">Replace with an image</label>
                <input type="file" id="thumbnail" name="thumbnail" accept="image/*">
                @error('thumbnail') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>
    </form>
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="film" :size="18" />
            <h3>This movie</h3>
        </div>
        <dl class="kv-list">
            <div><dt>Views</dt><dd>{{ number_format($movie->views) }}</dd></div>
            <div><dt>Length</dt><dd>{{ $movie->durationLabel() ?? 'Unknown' }}</dd></div>
            <div><dt>File</dt><dd>{{ strtoupper(pathinfo($movie->video_path, PATHINFO_EXTENSION)) }}, {{ \App\Models\DriveFile::humanSize($movie->video_size) }}</dd></div>
            <div><dt>Uploaded</dt><dd>{{ $movie->created_at->format('j M Y') }}</dd></div>
        </dl>
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="trash" :size="18" />
            <h3>Delete</h3>
        </div>
        <p class="small muted">Removes the video, thumbnail and subtitles for everyone.</p>
        <form method="POST" action="{{ route('movies.destroy', $movie) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger btn-sm btn-block" type="button"
                    data-confirm="The video, its thumbnail and subtitles are deleted for everyone."
                    data-confirm-title="Delete this movie?" data-confirm-label="Delete movie">
                Delete movie
            </button>
        </form>
    </section>
@endsection
