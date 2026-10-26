@extends('layouts.app')

@section('title', 'Upload a movie')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media.css') }}?v={{ filemtime(public_path('css/media.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/uploader.js') }}?v={{ filemtime(public_path('js/uploader.js')) }}" defer></script>
    <script src="{{ asset('js/movies.js') }}?v={{ filemtime(public_path('js/movies.js')) }}" defer></script>
@endpush

@section('content')
    <div class="page-head">
        <div>
            <h1>Upload a movie</h1>
            <p>Choose a video, fill in the details, then press Upload. Everyone on the network can watch it.</p>
        </div>
        <div class="spacer"></div>
        <a class="btn btn-ghost" href="{{ route('movies.index') }}"><x-icon name="back" :size="16" /> Back to movies</a>
    </div>

    <form class="mv-upload" data-movie-upload action="{{ route('movies.store') }}"
          data-chunk-url="{{ route('uploads.chunk') }}" data-lookup-url="{{ route('movies.lookup') }}" novalidate>

        {{-- 1. The video --------------------------------------------------- --}}
        <section class="up-step">
            <div class="up-step-head">
                <span class="up-num">1</span>
                <div>
                    <h2>Choose the video</h2>
                    <p>MP4 (H.264 video, AAC audio) plays in every browser. WebM works too. MKV and MOV may not play everywhere.</p>
                </div>
            </div>

            <label class="dropzone up-drop" data-video-drop for="mv-video">
                <span class="dz-icon"><x-icon name="film" :size="34" /></span>
                <span class="dz-main">Drag your video here</span>
                <span class="dz-or">or</span>
                <span class="btn"><x-icon name="upload" :size="16" /> Choose a video file</span>
            </label>
            <input type="file" id="mv-video" accept="video/*,.mkv,.mp4,.m4v,.webm,.mov" hidden data-video-input>

            <div class="mv-picked" data-video-picked hidden>
                <span class="mv-picked-icon"><x-icon name="film" :size="20" /></span>
                <span class="mv-picked-body">
                    <span class="mv-picked-name" data-video-name></span>
                    <span class="tiny muted" data-video-size></span>
                </span>
                <button class="btn btn-ghost btn-sm" type="button" data-video-change>Choose another</button>
            </div>

            <p class="mv-warning" data-format-warning hidden>
                <x-icon name="shield" :size="17" />
                <span>This format may not play in every browser. Converting it to MP4 (H.264 / AAC) first gives the best results.</span>
            </p>
        </section>

        <div data-details hidden>
            {{-- 2. Details ------------------------------------------------------ --}}
            <section class="up-step">
                <div class="up-step-head">
                    <span class="up-num">2</span>
                    <div>
                        <h2>Details</h2>
                        <p data-lookup-note>We filled in a title from the file name. Change anything you like.</p>
                    </div>
                </div>

                <div class="field">
                    <label for="mv-title">Title <span class="req">*</span></label>
                    <input type="text" id="mv-title" name="title" maxlength="150" required>
                </div>

                <div class="field">
                    <label for="mv-description">Description</label>
                    <textarea id="mv-description" name="description" rows="4" maxlength="5000"
                              placeholder="What is it about? Cast, year, language..."></textarea>
                </div>

                <div class="field" style="max-width:320px;margin-bottom:0">
                    <label for="mv-category">Category</label>
                    <select id="mv-category" name="category">
                        <option value="">Choose a category</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            {{-- 3. Thumbnail ---------------------------------------------------- --}}
            <section class="up-step">
                <div class="up-step-head">
                    <span class="up-num">3</span>
                    <div>
                        <h2>Thumbnail</h2>
                        <p data-frame-note>Pick one of the frames from your video, or upload your own image.</p>
                    </div>
                </div>

                <div class="up-thumb">
                    <div class="mv-thumb-preview" data-thumb-preview>
                        <span class="small muted">Picking frames from the video&hellip;</span>
                    </div>
                    <div>
                        <div class="mv-frames" data-frames></div>
                        <label class="btn btn-ghost btn-sm" for="mv-thumb" style="cursor:pointer;margin-top:.75rem">
                            <x-icon name="image" :size="15" /> Use my own image instead
                        </label>
                        <input type="file" id="mv-thumb" accept="image/*" hidden data-thumb-input>
                    </div>
                </div>
            </section>

            {{-- 4. Subtitles ---------------------------------------------------- --}}
            <section class="up-step">
                <div class="up-step-head">
                    <span class="up-num">4</span>
                    <div>
                        <h2>Subtitles <span class="muted" style="font-weight:400">(optional)</span></h2>
                        <p>Add .srt or .vtt files, up to 6. Check the language of each one.</p>
                    </div>
                </div>

                <div class="mv-subs" data-sub-list></div>
                <label class="btn btn-ghost btn-sm" for="mv-subs" style="cursor:pointer">
                    <x-icon name="captions" :size="15" /> Add subtitle files
                </label>
                <input type="file" id="mv-subs" accept=".srt,.vtt" multiple hidden data-sub-input>
            </section>
        </div>

        {{-- Progress and submit -------------------------------------------------- --}}
        <div class="mv-progressbox" data-progressbox hidden>
            <div class="mv-progress-row">
                <strong data-progress-label>Uploading&hellip;</strong>
                <span class="tiny muted" data-progress-stats></span>
            </div>
            <div class="bar-track"><i data-progress-bar></i></div>
            <p class="tiny muted" style="margin:.5rem 0 0">Keep this tab open until the upload finishes.</p>
        </div>

        <p class="error-text up-error" data-upload-error hidden></p>

        <div class="mv-upload-actions" data-actions hidden>
            <a class="btn btn-ghost" href="{{ route('movies.index') }}" data-cancel>Cancel</a>
            <button class="btn" type="submit" data-submit>
                <x-icon name="upload" :size="16" /> Upload movie
            </button>
        </div>
    </form>

    {{-- Language options, reused by the subtitle rows built in movies.js. --}}
    <template data-lang-options>
        @foreach ($languages as $code => $name)
            <option value="{{ $code }}">{{ $name }}</option>
        @endforeach
    </template>
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="sparkle" :size="18" />
            <h3>Good to know</h3>
        </div>
        <ul class="mv-tips">
            <li><b>Big files are fine.</b> The video uploads in 8 MB pieces and a failed piece is retried, so a slow connection does not ruin a 2 GB upload.</li>
            <li><b>MP4 with H.264 and AAC</b> plays everywhere and lets viewers skip around instantly.</li>
            <li><b>Subtitles</b> can be resized and re-timed by viewers while they watch.</li>
            <li>You can change the title, thumbnail and subtitles later with <b>Edit</b> on the movie page.</li>
        </ul>
    </section>
@endsection
