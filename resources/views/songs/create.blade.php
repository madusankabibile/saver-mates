@extends('songs.layout')

@section('title', 'Upload songs')

@section('music')
    <div class="mu-section-head">
        <div>
            <h2>Upload songs</h2>
            <p class="small muted">Add as many songs as you like. You can keep listening while they upload.</p>
        </div>
    </div>

    <ol class="up-how">
        <li><span class="up-num">1</span><span><b>Choose all your songs at once</b><br>MP3, M4A, AAC, OGG, OPUS, WAV or FLAC. Name each file after its song.</span></li>
        <li><span class="up-num">2</span><span><b>Details fill in by themselves</b><br>Artist, album, genre and cover art are found from each song's name.</span></li>
        <li><span class="up-num">3</span><span><b>Change anything, any time</b><br>Fix details in the list below, or later with <b>Edit details</b> on any song you uploaded.</span></li>
    </ol>

    <div class="mu-upload" data-song-upload
         data-chunk-url="{{ route('uploads.chunk') }}" data-store-url="{{ route('songs.store') }}">
        <label class="dropzone up-drop up-drop-sm" data-song-drop>
            <span class="dz-icon"><x-icon name="music" :size="30" /></span>
            <span class="dz-main">Drag songs here</span>
            <span class="dz-or">or</span>
            <span class="btn"><x-icon name="upload" :size="16" /> Choose songs</span>
            <input type="file" accept="audio/*,.mp3,.m4a,.aac,.ogg,.opus,.wav,.flac" multiple hidden data-song-input>
        </label>

        <div class="mu-upload-summary" data-song-summary hidden>
            <span data-song-summary-text></span>
            <a class="btn btn-sm" href="{{ route('songs.index') }}" data-music-link>Go to all songs</a>
        </div>

        <div class="mu-upload-list" data-song-list></div>
    </div>
@endsection
