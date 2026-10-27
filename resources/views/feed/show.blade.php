@extends('layouts.app')

@section('title', $post->isArticle() ? $post->title : 'Post by '.$post->user->name)

@section('content')
    @php
        $back = url()->previous() === url()->current() ? route('feed.index') : url()->previous();
    @endphp

    @if ($post->isArticle())
        <div class="post-page-layout">
            <article class="article-page card">
                <div class="article-top-nav">
                    <a class="btn btn-ghost btn-sm" href="{{ $back }}">
                        <x-icon name="back" :size="15" /> Back
                    </a>
                </div>

                <header class="article-head">
                    <h1>{{ $post->title }}</h1>

                    <div class="article-byline">
                        <a href="{{ route('profile.show', $post->user) }}">
                            <x-avatar :user="$post->user" :size="42" :presence="true" />
                        </a>

                        <div class="ab-body">
                            <a class="ab-name" href="{{ route('profile.show', $post->user) }}">{{ $post->user->name }}</a>
                            <div class="ab-meta">
                                {{ $post->user->designation ?: '@'.$post->user->username }}
                            </div>
                        </div>

                        <span class="read-meta" style="margin-left:auto">
                            {{ $post->created_at->format('j M Y') }} &middot; {{ $post->readingMinutes() }} min read
                        </span>

                        @if (auth()->check() && auth()->id() === $post->user_id)
                            <form method="POST" action="{{ route('posts.destroy', $post) }}">
                                @csrf
                                @method('DELETE')
                                <button class="mini-btn is-danger" type="button" aria-label="Delete article"
                                        data-confirm="This article and its comments will be removed for everyone."
                                        data-confirm-title="Delete this article?" data-confirm-label="Delete article">
                                    <x-icon name="trash" :size="15" />
                                </button>
                            </form>
                        @endif
                    </div>
                </header>

                @if ($post->images->isNotEmpty())
                    <figure class="article-cover">
                        <img src="{{ $post->images->first()->url() }}" alt="Cover image for {{ $post->title }}">
                    </figure>
                @endif

                {{-- Rendered article body with sanitized rich text HTML. --}}
                <div class="prose article-body">{!! $post->renderedBody() !!}</div>

                @if ($post->images->count() > 1)
                    <div class="post-images n-{{ min($post->images->count() - 1, 4) }}" style="border-radius:var(--r-md);overflow:hidden;margin:1.5rem 0">
                        @foreach ($post->images->slice(1)->take(4) as $image)
                            <a href="{{ $image->url() }}" data-lightbox="{{ $image->url() }}" data-mime="image/*">
                                <img src="{{ $image->url() }}" alt="Image from {{ $post->title }}" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- Reactions and comments reuse the standard post footer. --}}
                <div class="article-engagement">
                    @include('feed.partials.post-engagement', ['post' => $post, 'expanded' => true])
                </div>
            </article>
        </div>
    @else
        <div class="post-page-layout">
            <div class="page-head" style="margin-bottom:1rem">
                <a class="btn btn-ghost btn-sm" href="{{ $back }}">
                    <x-icon name="back" :size="15" /> Back
                </a>
            </div>
            @include('feed.partials.post', ['post' => $post, 'expanded' => true])
        </div>
    @endif
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="user" :size="18" />
            <h3>{{ $post->isArticle() ? 'About the author' : 'Posted by' }}</h3>
        </div>

        <a class="person-row" href="{{ route('profile.show', $post->user) }}">
            <x-avatar :user="$post->user" :size="42" :presence="true" />
            <span class="pr-body">
                <span class="pr-name">{{ $post->user->name }}</span>
                <span class="pr-meta">{{ $post->user->designation ?: '@'.$post->user->username }}</span>
            </span>
        </a>

        @if ($post->user->bio)
            <p class="small muted" style="margin-top:.6rem">{{ \Illuminate\Support\Str::limit($post->user->bio, 180) }}</p>
        @endif

        @if (auth()->check() && auth()->id() !== $post->user_id)
            <a class="btn btn-sm btn-block" href="{{ route('chat.with', $post->user) }}" style="margin-top:.8rem">
                <x-icon name="chat" :size="15" /> Send a message
            </a>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="feed" :size="18" />
            <h3>This {{ $post->isArticle() ? 'article' : 'post' }}</h3>
        </div>

        <dl class="kv-list">
            <div><dt>Published</dt><dd>{{ $post->created_at->format('j M Y') }}</dd></div>
            <div><dt>Reactions</dt><dd>{{ number_format($post->reactions->count()) }}</dd></div>
            <div><dt>Comments</dt><dd>{{ number_format($post->comments_count) }}</dd></div>
        </dl>

        <a class="btn btn-ghost btn-sm btn-block" href="{{ route('feed.index') }}" style="margin-top:.85rem">
            <x-icon name="back" :size="15" /> Back to the newsfeed
        </a>
    </section>
@endsection
