@php
    $themes = \App\Models\Post::THEMES;
    $mode = old('type', 'standard');
    $theme = old('theme', array_key_first($themes));
@endphp

<form class="composer" method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data" data-composer>
    @csrf
    <input type="hidden" name="type" value="{{ $mode }}">
    <input type="hidden" name="theme" value="{{ $theme }}">

    <header class="composer-head">
        <x-avatar :user="auth()->user()" :size="42" />

        <div class="composer-modes" role="tablist" aria-label="What are you posting?">
            <button class="composer-mode" type="button" role="tab" data-mode="standard">
                <x-icon name="feed" :size="15" /> Quick post
            </button>
            <button class="composer-mode" type="button" role="tab" data-mode="article">
                <x-icon name="pencil" :size="15" /> Article
            </button>
            <button class="composer-mode" type="button" role="tab" data-mode="colored">
                <x-icon name="palette" :size="15" /> Colour card
            </button>
        </div>
    </header>

    {{-- Article title ------------------------------------------------- --}}
    <div class="composer-title" data-article-only hidden>
        <input type="text" name="title" value="{{ old('title') }}" maxlength="180"
               placeholder="Article title" aria-label="Article title"
               class="{{ $errors->has('title') ? 'input-error' : '' }}">
        @error('title')
            <p class="error-text">{{ $message }}</p>
        @enderror
    </div>

    {{-- Modern Rich Text Toolbar for Articles ------------------------- --}}
    <div class="rt-bar" data-article-only hidden>
        <div class="rt-tools">
            {{-- Text formatting: Bold, Italic, Underline --}}
            <div class="rt-group">
                <button class="md-btn" type="button" data-cmd="bold" title="Bold (Ctrl+B)" aria-label="Bold"><b>B</b></button>
                <button class="md-btn" type="button" data-cmd="italic" title="Italic (Ctrl+I)" aria-label="Italic"><i>I</i></button>
                <button class="md-btn" type="button" data-cmd="underline" title="Underline (Ctrl+U)" aria-label="Underline"><u>U</u></button>
            </div>

            <span class="md-sep"></span>

            {{-- Alignment: Left, Center, Right --}}
            <div class="rt-group">
                <button class="md-btn" type="button" data-cmd="justifyLeft" title="Align Left" aria-label="Align Left">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h10M4 18h14"/></svg>
                </button>
                <button class="md-btn" type="button" data-cmd="justifyCenter" title="Align Center" aria-label="Align Center">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M5 18h14"/></svg>
                </button>
                <button class="md-btn" type="button" data-cmd="justifyRight" title="Align Right" aria-label="Align Right">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M10 12h10M6 18h14"/></svg>
                </button>
            </div>

            <span class="md-sep"></span>

            {{-- Text Color --}}
            <div class="rt-group color-tool-wrap">
                <button class="md-btn color-trigger" type="button" data-color-trigger="text" title="Text Color" aria-label="Text Color">
                    <span class="color-btn-inner">
                        <span class="color-letter">A</span>
                        <span class="color-swatch-bar" data-color-bar style="background:#2563eb"></span>
                    </span>
                </button>

                {{-- Color popover menu --}}
                <div class="color-popover" data-color-popover hidden>
                    <div class="color-popover-title">Text Color</div>
                    <div class="color-palette-grid">
                        <button type="button" class="color-dot" style="background:#111827" data-color="#111827" title="Dark Ink"></button>
                        <button type="button" class="color-dot" style="background:#4b5563" data-color="#4b5563" title="Slate Gray"></button>
                        <button type="button" class="color-dot" style="background:#2563eb" data-color="#2563eb" title="Royal Blue"></button>
                        <button type="button" class="color-dot" style="background:#0284c7" data-color="#0284c7" title="Sky Blue"></button>
                        <button type="button" class="color-dot" style="background:#059669" data-color="#059669" title="Emerald Green"></button>
                        <button type="button" class="color-dot" style="background:#16a34a" data-color="#16a34a" title="Leaf Green"></button>
                        <button type="button" class="color-dot" style="background:#d97706" data-color="#d97706" title="Amber Orange"></button>
                        <button type="button" class="color-dot" style="background:#ea580c" data-color="#ea580c" title="Sunset Orange"></button>
                        <button type="button" class="color-dot" style="background:#dc2626" data-color="#dc2626" title="Crimson Red"></button>
                        <button type="button" class="color-dot" style="background:#db2777" data-color="#db2777" title="Rose Pink"></button>
                        <button type="button" class="color-dot" style="background:#7c3aed" data-color="#7c3aed" title="Purple"></button>
                        <button type="button" class="color-dot" style="background:#0d9488" data-color="#0d9488" title="Teal"></button>
                    </div>
                    <div class="color-custom-row">
                        <span>Custom:</span>
                        <input type="color" data-color-picker value="#2563eb" title="Choose any custom color">
                        <button type="button" class="color-clear-btn" data-color-clear title="Reset color">Default</button>
                    </div>
                </div>
            </div>

            <span class="md-sep"></span>

            {{-- Lists: Bulleted, Numbered --}}
            <div class="rt-group">
                <button class="md-btn" type="button" data-cmd="insertUnorderedList" title="Bulleted list" aria-label="Bulleted list">&bull; List</button>
                <button class="md-btn" type="button" data-cmd="insertOrderedList" title="Numbered list" aria-label="Numbered list">1. List</button>
            </div>

            <span class="md-sep"></span>

            {{-- Image upload --}}
            <div class="rt-group">
                <button class="md-btn" type="button" data-action="insert-image" title="Upload or insert image" aria-label="Insert Image">
                    <x-icon name="image" :size="15" /> Image
                </button>
                <input type="file" accept="image/*" hidden data-article-inline-file>
            </div>
        </div>
    </div>

    {{-- Editor ---------------------------------------------------------- --}}
    <div class="composer-editor">
        {{-- Standard plain text for quick post and colour card --}}
        <textarea name="body" rows="2" maxlength="100000"
                  placeholder="Share an update with the network"
                  data-composer-text>{{ old('body') }}</textarea>

        {{-- Modern rich text editor for article --}}
        <div class="article-editor prose" contenteditable="true"
             data-article-editor
             data-placeholder="Write your article... Style with headings, colored text, lists, and images."
             spellcheck="true" hidden>{!! old('type') === 'article' ? old('body') : '' !!}</div>
    </div>

    {{-- Colour card ----------------------------------------------------- --}}
    <div class="theme-row" data-theme-row hidden>
        @foreach ($themes as $key => $item)
            <button class="theme-swatch {{ $key === $theme ? 'is-active' : '' }}" type="button"
                    data-theme="{{ $key }}" data-css="{{ $item['css'] }}"
                    style="background:{{ $item['css'] }}"
                    title="{{ $item['label'] }}" aria-label="{{ $item['label'] }} background"></button>
        @endforeach
    </div>

    <div class="colored-preview" data-colored-preview hidden
         style="background:{{ $themes[$theme]['css'] ?? $themes['violet']['css'] }}">Your words, on colour</div>

    <div class="attach-previews" data-composer-thumbs hidden></div>

    <footer class="composer-foot">
        <span class="composer-attach" data-image-block>
            <label class="md-btn md-btn-wide" for="composer-images" data-photos-label>
                <x-icon name="image" :size="15" /> <span data-photos-text>Photos</span>
            </label>
            <input id="composer-images" type="file" name="images[]" accept="image/*" multiple
                   hidden data-composer-files>
        </span>

        <span class="tiny muted" data-composer-hint>Up to 4 photos</span>

        <button class="btn" type="submit" data-composer-submit>
            <x-icon name="send" :size="16" /> Post
        </button>
    </footer>
</form>
