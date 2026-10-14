@extends('songs.layout')

@section('title', 'Edit '.$song->title)

@section('music')
    <div class="mu-section-head">
        <div>
            <h2>Edit song</h2>
            <p class="small muted">{{ $song->title }} &middot; {{ $song->durationLabel() }} &middot; {{ \App\Models\DriveFile::humanSize($song->size) }}</p>
        </div>
    </div>

    {{-- Search by name and pick the right song to fill in its details. --}}
    <section class="mu-lookup" data-song-lookup="{{ route('songs.lookup', $song) }}" data-apply-url="{{ route('songs.lookup.apply', $song) }}">
        <div class="mu-lookup-head">
            <span class="mu-lookup-icon"><x-icon name="search" :size="16" /></span>
            <div>
                <b>Find song details</b>
                <span class="small muted">Search by the song's name, then pick the right one to fill in its artist, album, genre and cover.</span>
            </div>
        </div>
        <div class="mu-lookup-bar">
            <input type="search" value="{{ trim($song->title.' '.$song->artist) }}" maxlength="200"
                   aria-label="Song name to search for" data-lookup-q>
            <button class="btn btn-sm" type="button" data-lookup-go>Search</button>
        </div>
        @error('lookup') <p class="error-text">{{ $message }}</p> @enderror
        <div class="mu-lookup-results" data-lookup-results></div>
    </section>

    <form method="POST" action="{{ route('songs.update', $song) }}" enctype="multipart/form-data" class="mu-edit">
        @csrf

        <div class="mu-edit-cover">
            <span class="mu-cover mu-cover-xl" style="--hue: {{ $song->hue() }}">
                @if ($song->coverUrl())
                    <img src="{{ $song->coverUrl() }}" alt="Cover art">
                @else
                    <x-icon name="music" :size="44" />
                @endif
            </span>
            <label class="btn btn-ghost btn-sm btn-block" for="cover" style="cursor:pointer;margin-top:.6rem">
                <x-icon name="image" :size="15" /> Change cover
            </label>
            <input type="file" id="cover" name="cover" accept="image/*" hidden>
            @if ($song->cover_path)
                <label class="checkline" style="margin-top:.5rem">
                    <input type="checkbox" name="remove_cover" value="1"> Remove cover
                </label>
            @endif
            @error('cover') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="field">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="{{ old('title', $song->title) }}" maxlength="250" required
                       class="{{ $errors->has('title') ? 'input-error' : '' }}">
                @error('title') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="artist">Artist</label>
                    <input type="text" id="artist" name="artist" value="{{ old('artist', $song->artist) }}" maxlength="250">
                </div>
                <div class="field">
                    <label for="album">Album</label>
                    <input type="text" id="album" name="album" value="{{ old('album', $song->album) }}" maxlength="250">
                </div>
            </div>
            <div class="field">
                <label for="genre">Genre</label>
                <input type="text" id="genre" name="genre" value="{{ old('genre', $song->genre) }}" maxlength="60" placeholder="Pop, Classical, Baila...">
            </div>

            <div class="mu-edit-actions">
                <button class="btn" type="submit">Save changes</button>
                <a class="btn btn-ghost" href="{{ route('songs.index') }}">Cancel</a>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('songs.destroy', $song) }}" class="mu-delete">
        @csrf
        @method('DELETE')
        <span class="small muted">Deleting removes the audio file and takes the song out of every playlist.</span>
        <button class="btn btn-danger btn-sm" type="button"
                data-confirm="The audio file is deleted and the song disappears from every playlist."
                data-confirm-title="Delete this song?" data-confirm-label="Delete song">
            <x-icon name="trash" :size="15" /> Delete song
        </button>
    </form>
@endsection
