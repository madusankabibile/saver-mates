@extends('layouts.app')

@section('title', 'Upload software')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/media.css') }}?v={{ filemtime(public_path('css/media.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/uploader.js') }}?v={{ filemtime(public_path('js/uploader.js')) }}" defer></script>
    <script src="{{ asset('js/software.js') }}?v={{ filemtime(public_path('js/software.js')) }}" defer></script>
@endpush

@section('content')
    <div class="page-head">
        <div>
            <h1>Upload software</h1>
            <p>Choose a file, fill in the details, then press Upload. Everyone on the network can download it.</p>
        </div>
        <div class="spacer"></div>
        <a class="btn btn-ghost" href="{{ route('software.index') }}"><x-icon name="back" :size="16" /> Back to software</a>
    </div>

    <form class="mv-upload" data-sw-upload
          action="{{ route('software.store') }}"
          data-chunk-url="{{ route('uploads.chunk') }}"
          data-lookup-url="{{ route('software.lookup') }}"
          novalidate>
        @csrf

        {{-- 1. The file --------------------------------------------------- --}}
        <section class="up-step">
            <div class="up-step-head">
                <span class="up-num">1</span>
                <div>
                    <h2>Choose the file</h2>
                    <p>EXE, MSI, DMG, PKG, DEB, RPM, APK, ZIP, AppImage, Snap and Flatpak are all accepted. Any other application format works too.</p>
                </div>
            </div>

            <label class="dropzone sw-drop" data-sw-drop for="sw-file">
                <span class="dz-icon sw-drop-icon"><x-icon name="package" :size="34" /></span>
                <span class="dz-main">Drag your software file here</span>
                <span class="dz-or">or</span>
                <span class="btn"><x-icon name="upload" :size="16" /> Choose a file</span>
                <span class="sw-drop-hint">.exe .msi .dmg .pkg .deb .rpm .apk .zip .AppImage .snap .flatpak and more</span>
            </label>
            <input type="file" id="sw-file"
                   accept=".exe,.msi,.dmg,.pkg,.deb,.rpm,.apk,.zip,.tar.gz,.AppImage,.snap,.flatpak,.7z,.rar,.run,.sh"
                   hidden data-sw-input>

            <div class="mv-picked" data-sw-picked hidden>
                <span class="mv-picked-icon"><x-icon name="package" :size="20" /></span>
                <span class="mv-picked-body">
                    <span class="mv-picked-name" data-sw-name></span>
                    <span class="tiny muted" data-sw-size></span>
                </span>
                <button class="btn btn-ghost btn-sm" type="button" data-sw-change>Choose another</button>
            </div>
        </section>

        <div data-sw-details hidden>
            {{-- 2. Details ------------------------------------------------------ --}}
            <section class="up-step">
                <div class="up-step-head">
                    <span class="up-num">2</span>
                    <div>
                        <h2>Details</h2>
                        <p data-sw-lookup-note>Fill in the details below. We'll try to look up info from the file name.</p>
                    </div>
                </div>

                <div class="field">
                    <label for="sw-title">Title <span class="req">*</span></label>
                    <input type="text" id="sw-title" name="title" maxlength="200" required
                           class="{{ $errors->has('title') ? 'input-error' : '' }}"
                           value="{{ old('title') }}">
                    @error('title') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="sw-description">Description</label>
                    <textarea id="sw-description" name="description" rows="4" maxlength="2000"
                              placeholder="What does this software do? Who made it? Any usage notes…">{{ old('description') }}</textarea>
                    @error('description') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="field">
                        <label for="sw-version">Version</label>
                        <input type="text" id="sw-version" name="version" maxlength="40"
                               placeholder="e.g. 2.4.1" value="{{ old('version') }}">
                        @error('version') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label for="sw-os_type">Operating system</label>
                        <select id="sw-os_type" name="os_type">
                            <option value="">Unknown / not specified</option>
                            @foreach ($osTypes as $os)
                                <option value="{{ $os }}" @selected(old('os_type') === $os)>{{ $os }}</option>
                            @endforeach
                        </select>
                        @error('os_type') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="field" style="max-width:320px;margin-bottom:0">
                    <label for="sw-category">Category</label>
                    <select id="sw-category" name="category">
                        <option value="">Choose a category</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </section>

            {{-- 3. Thumbnail ---------------------------------------------------- --}}
            <section class="up-step">
                <div class="up-step-head">
                    <span class="up-num">3</span>
                    <div>
                        <h2>Thumbnail <span class="muted" style="font-weight:400">(optional)</span></h2>
                        <p>An icon or screenshot helps people recognise the software at a glance.</p>
                    </div>
                </div>

                <div class="up-thumb">
                    <div class="mv-thumb-preview" data-sw-thumb-preview>
                        <span class="small muted">No thumbnail chosen</span>
                    </div>
                    <div>
                        <label class="btn btn-ghost btn-sm" for="sw-thumb" style="cursor:pointer">
                            <x-icon name="image" :size="15" /> Choose an image
                        </label>
                        <input type="file" id="sw-thumb" name="thumbnail" accept="image/*" hidden data-sw-thumb-input>
                        @error('thumbnail') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Gallery picker: shown when FileCR returns multiple images --}}
                <div class="sw-gallery-picker" data-sw-gallery hidden>
                    <p class="small muted" style="margin:0 0 .5rem">Select a thumbnail from the gallery:</p>
                </div>
            </section>
        </div>

        {{-- Progress and submit -------------------------------------------------- --}}
        <div class="mv-progressbox" data-sw-progressbox hidden>
            <div class="mv-progress-row">
                <strong data-sw-progress-label>Uploading&hellip;</strong>
                <span class="tiny muted" data-sw-progress-stats></span>
            </div>
            <div class="bar-track"><i data-sw-progress-bar></i></div>
            <p class="tiny muted" style="margin:.5rem 0 0">Keep this tab open until the upload finishes.</p>
        </div>

        <p class="error-text up-error" data-sw-error hidden></p>

        <div class="mv-upload-actions" data-sw-actions hidden>
            <a class="btn btn-ghost" href="{{ route('software.index') }}" data-sw-cancel>Cancel</a>
            <button class="btn" type="submit" data-sw-submit>
                <x-icon name="upload" :size="16" /> Upload software
            </button>
        </div>
    </form>
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="sparkle" :size="18" />
            <h3>Good to know</h3>
        </div>
        <ul class="mv-tips">
            <li><b>Large files are fine.</b> The file uploads in 8 MB pieces, so a slow connection won't ruin a big upload.</li>
            <li><b>Any installer format</b> is accepted: EXE, MSI, DMG, PKG, DEB, RPM, APK, AppImage, Snap, Flatpak or a ZIP archive.</li>
            <li><b>Version numbers</b> help colleagues know whether they have the latest release.</li>
            <li>You can update the title, description and thumbnail later with <b>Edit</b> on the software page.</li>
        </ul>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-sw-upload]');
            if (form && window.initSoftwareUpload) initSoftwareUpload(form);
        });
    </script>
@endpush
