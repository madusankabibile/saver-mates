{{-- A root comment with its replies. Uses the newsfeed comment styles; posting and
     deleting go through movies.js so the video keeps playing. --}}
<div class="comment" data-comment="{{ $comment->id }}">
    <a href="{{ route('profile.show', $comment->user) }}">
        <x-avatar :user="$comment->user" :size="36" />
    </a>

    <div style="min-width:0;flex:1">
        <div class="comment-bubble">
            <div class="cb-head">
                <a class="cb-name" href="{{ route('profile.show', $comment->user) }}">{{ $comment->user->name }}</a>
                @if ($comment->user_id === $movie->user_id)
                    <span class="mchip mchip-sm">Uploader</span>
                @endif
                <span class="cb-time">{{ $comment->created_at->diffForHumans(null, true) }}</span>
            </div>
            <div class="cb-text">{{ $comment->body }}</div>
        </div>

        <div class="comment-tools">
            @auth
                <button type="button" data-reply-toggle="movie-reply-{{ $comment->id }}">Reply</button>
            @endauth
            @if (auth()->check() && in_array(auth()->id(), [$comment->user_id, $movie->user_id], true))
                <button type="button" data-delete-comment="{{ route('movie-comments.destroy', $comment) }}">Delete</button>
            @endif
        </div>

        <div class="comment-replies" data-replies @if ($comment->replies->isEmpty()) hidden @endif>
            @foreach ($comment->replies as $reply)
                @include('movies.partials.reply', ['comment' => $reply, 'movie' => $movie])
            @endforeach
        </div>

        @auth
            <form class="comment-form is-reply" id="movie-reply-{{ $comment->id }}" hidden
                  method="POST" action="{{ route('movie-comments.store', $movie) }}" data-movie-comment-form>
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <x-avatar :user="auth()->user()" :size="28" />
                <textarea name="body" rows="1" maxlength="2000" required
                          placeholder="Reply to {{ \Illuminate\Support\Str::of($comment->user->name)->explode(' ')->first() }}"></textarea>
                <button class="chat-send" type="submit" aria-label="Post reply" style="width:36px;height:36px">
                    <x-icon name="send" :size="15" />
                </button>
            </form>
        @endauth
    </div>
</div>
