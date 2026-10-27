@php
    /**
     * Reaction tally, action bar and comment thread.
     * Shared by the feed post card and the full article page.
     */
    $me = auth()->user();
    $mine = $post->reactionOf($me);
    $summary = $post->reactionSummary();
    $total = array_sum($summary);
    $commentCount = $post->comments_count ?? $post->comments()->count();
    $types = \App\Models\Reaction::TYPES;
    $palette = collect($types)->map(fn ($t) => ['emoji' => $t['emoji'], 'label' => $t['label']])->toJson();
    $expanded = $expanded ?? false;
@endphp

    <div class="post-tally" data-tally
         data-comment-link="{{ route('posts.show', $post) }}"
         data-comment-count="{{ $commentCount }} {{ \Illuminate\Support\Str::plural('comment', $commentCount) }}"
         @if (! $total && ! $commentCount) hidden @endif>
        @if ($total)
            <span class="tally-emojis">
                @foreach (array_slice(array_keys($summary), 0, 4) as $type)
                    <span>{!! \App\Models\Reaction::emoji($type) !!}</span>
                @endforeach
            </span>
            <span>{{ $total }} {{ \Illuminate\Support\Str::plural('reaction', $total) }}</span>
        @endif

        @if ($commentCount)
            <a class="tally-link" href="{{ route('posts.show', $post) }}">
                {{ $commentCount }} {{ \Illuminate\Support\Str::plural('comment', $commentCount) }}
            </a>
        @endif
    </div>

    <div class="post-actions">
        @auth
            <div class="react-wrap" data-post-id="{{ $post->id }}" data-palette="{{ $palette }}">
                <button class="post-action {{ $mine ? 'is-mine' : '' }}" type="button" data-react-open>
                    <span class="pa-emoji">{!! $mine ? \App\Models\Reaction::emoji($mine->type) : '&#128077;' !!}</span>
                    <span class="pa-label">{{ $mine ? \App\Models\Reaction::label($mine->type) : 'Like' }}</span>
                </button>

                <div class="reaction-pop" data-react-pop hidden>
                    @foreach ($types as $key => $type)
                        {{-- --i staggers the pop-in animation from left to right. --}}
                        <button type="button" data-react="{{ $key }}" style="--i: {{ $loop->index }}"
                                aria-label="{{ $type['label'] }}">{!! $type['emoji'] !!}</button>
                    @endforeach
                </div>
            </div>

            @if ($expanded)
                <button class="post-action" type="button"
                        onclick="document.getElementById('comment-box-{{ $post->id }}')?.focus()">
                    <x-icon name="comment" :size="17" /> Comment
                </button>
            @else
                <a class="post-action" href="{{ route('posts.show', $post) }}">
                    <x-icon name="comment" :size="17" /> Comment
                </a>
            @endif
        @else
            <a class="post-action" href="{{ route('login') }}" data-auth-open="login">
                <span class="pa-emoji">&#128077;</span> Sign in to react
            </a>
            <a class="post-action" href="{{ route('login') }}" data-auth-open="login">
                <x-icon name="comment" :size="17" /> Sign in to comment
            </a>
        @endauth
    </div>

    @if ($expanded || $post->rootComments->isNotEmpty())
        <div class="comments">
            @forelse ($post->rootComments as $comment)
                @include('feed.partials.comment', ['comment' => $comment, 'post' => $post])
            @empty
                <p class="small muted">No comments yet. Start the conversation.</p>
            @endforelse

            @auth
                <form class="comment-form" method="POST" action="{{ route('comments.store', $post) }}">
                    @csrf
                    <x-avatar :user="auth()->user()" :size="34" />
                    <textarea name="body" rows="1" maxlength="2000" required
                              id="comment-box-{{ $post->id }}" placeholder="Write a comment"></textarea>
                    <button class="chat-send" type="submit" aria-label="Post comment" style="width:40px;height:40px">
                        <x-icon name="send" :size="16" />
                    </button>
                </form>
            @else
                <p class="small muted" style="margin-top:.8rem">
                    <a href="{{ route('login') }}" data-auth-open="login">Sign in</a> to join the conversation.
                </p>
            @endauth
        </div>
    @endif
