@extends('layouts.app')

@section('title', 'Edit '.$software->title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media.css') }}?v={{ filemtime(public_path('css/media.css')) }}">
@endpush

@section('content')
    <div class="page-head">
        <div>
            <h1>Edit software</h1>
            <p>{{ $software->title }}</p>
        </div>
        <div class="spacer"></div>
        <a class="btn btn-ghost" href="{{ route('software.show', $software) }}">
            <x-icon name="back" :size="16" /> Back
        </a>
    </div>

    <form method="POST" action="{{ route('software.update', $software) }}" enctype="multipart/form-data" class="mv-upload-grid">
        @csrf

        <div>
            <div class="field">
                <label for="title">Title <span class="req">*</span></label>
                <input type="text" id="title" name="title"
                       value="{{ old('title', $software->title) }}" maxlength="200" required
                       class="{{ $errors->has('title') ? 'input-error' : '' }}">
                @error('title') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="6" maxlength="2000">{{ old('description', $software->description) }}</textarea>
                @error('description') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="field">
                    <label for="version">Version</label>
                    <input type="text" id="version" name="version" maxlength="40"
                           placeholder="e.g. 2.4.1"
                           value="{{ old('version', $software->version) }}">
                    @error('version') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="os_type">Operating system</label>
                    <select id="os_type" name="os_type">
                        <option value="">Unknown / not specified</option>
                        @foreach ($osTypes as $os)
                            <option value="{{ $os }}" @selected(old('os_type', $software->os_type) === $os)>{{ $os }}</option>
                        @endforeach
                    </select>
                    @error('os_type') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">No category</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}" @selected(old('category', $software->category) === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                @error('category') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <button class="btn" type="submit">Save changes</button>
        </div>

        <div>
            <span class="label">Thumbnail</span>
            <div class="mv-thumb-preview" style="--hue: {{ $software->posterHue() }}">
                @if ($software->thumbnailUrl())
                    <img src="{{ $software->thumbnailUrl() }}" alt="Current thumbnail" id="sw-thumb-preview">
                @else
                    <span class="small muted" id="sw-thumb-preview">No thumbnail yet.</span>
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
            <x-icon name="package" :size="18" />
            <h3>This software</h3>
        </div>
        <dl class="kv-list">
            <div><dt>Downloads</dt><dd>{{ number_format($software->downloads) }}</dd></div>
            <div><dt>Views</dt><dd>{{ number_format($software->views) }}</dd></div>
            <div><dt>File</dt><dd>{{ $software->sizeLabel() }}</dd></div>
            <div><dt>Uploaded</dt><dd>{{ $software->created_at->format('j M Y') }}</dd></div>
        </dl>
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="trash" :size="18" />
            <h3>Delete</h3>
        </div>
        <p class="small muted">Removes the file and thumbnail for everyone.</p>
        <form method="POST" action="{{ route('software.destroy', $software) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger btn-sm btn-block" type="button"
                    data-confirm="The software file and thumbnail are permanently deleted for everyone."
                    data-confirm-title="Delete this software?" data-confirm-label="Delete software">
                Delete software
            </button>
        </form>
    </section>
@endsection
