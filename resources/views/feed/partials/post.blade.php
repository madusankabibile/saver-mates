@php
    // Reactions and comments live in the post-engagement partial below.
    $me = auth()->user();
    $expanded = $expanded ?? false;
@endphp

<article class="post" id="post-{{ $post->id }}">
    <header class="post-head">
        <a href="{{ route('profile.show', $post->user) }}">
            <x-avatar :user="$post->user" :size="48" :presence="true" />
        </a>

        <div class="ph-body">
            <a class="post-author" href="{{ route('profile.show', $post->user) }}">{{ $post->user->name }}</a>
            @if ($post->user->designation)
                <div class="post-role">{{ $post->user->designation }}</div>
            @endif
            <div class="post-time">
                <a href="{{ route('posts.show', $post) }}" style="color:inherit"
                   title="{{ $post->created_at->format('D, j M Y g:i a') }}">
                    {{ $post->created_at->diffForHumans() }}
                </a>
                @if ($post->isArticle())
                    <span class="read-meta" style="margin-left:.4rem">
                        <x-icon name="pencil" :size="11" /> {{ $post->readingMinutes() }} min read
                    </span>
                @endif
            </div>
        </div>

        @if ($me && $me->id === $post->user_id)
            <form method="POST" action="{{ route('posts.destroy', $post) }}">
                @csrf
                @method('DELETE')
                <button class="mini-btn is-danger" type="button" aria-label="Delete post"
                        data-confirm="This post and its comments will be removed for everyone."
                        data-confirm-title="Delete this post?" data-confirm-label="Delete post">
                    <x-icon name="trash" :size="15" />
                </button>
            </form>
        @endif
    </header>

    @if ($post->isArticle())
        {{-- A teaser on the feed; the full piece lives on its own page. --}}
        <div class="post-article">
            <a class="pa-title" href="{{ route('posts.show', $post) }}">{{ $post->title }}</a>
            <p class="pa-excerpt">{{ $post->excerpt() }}</p>
            <a class="pa-more" href="{{ route('posts.show', $post) }}">
                Read article <x-icon name="next" :size="14" />
            </a>
        </div>

        @if ($post->firstImageUrl())
            <div class="post-images n-1" style="margin-top:1rem">
                <a href="{{ route('posts.show', $post) }}">
                    <img src="{{ $post->firstImageUrl() }}"
                         alt="Cover image for {{ $post->title }}" loading="lazy">
                </a>
            </div>
        @endif
    @elseif ($post->type === 'colored')
        <div class="post-colored" style="background:{{ $post->themeCss() }};font-size:{{ $post->coloredFontSize() }}">{{ $post->body }}</div>
    @else
        @if (filled($post->body))
            <div class="post-body">{{ $post->body }}</div>
        @endif

        @if ($post->images->isNotEmpty())
            <div class="post-images n-{{ min($post->images->count(), 4) }}">
                @foreach ($post->images->take(4) as $image)
                    <a href="{{ $image->url() }}" data-lightbox="{{ $image->url() }}" data-mime="image/*">
                        <img src="{{ $image->url() }}" alt="Attachment from {{ $post->user->name }}" loading="lazy">
                    </a>
                @endforeach
            </div>
        @endif
    @endif

    @include('feed.partials.post-engagement', ['post' => $post, 'expanded' => $expanded])
</article>
