<div class="comment" data-comment="{{ $comment->id }}">
    <a href="{{ route('profile.show', $comment->user) }}">
        <x-avatar :user="$comment->user" :size="28" />
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

        @if (auth()->check() && in_array(auth()->id(), [$comment->user_id, $movie->user_id], true))
            <div class="comment-tools">
                <button type="button" data-delete-comment="{{ route('movie-comments.destroy', $comment) }}">Delete</button>
            </div>
        @endif
    </div>
</div>
