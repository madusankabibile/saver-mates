@php
    $me = auth()->user();
    $canRemove = $me && ($me->id === $comment->user_id || $me->id === $post->user_id);
@endphp

<div class="comment">
    <a href="{{ route('profile.show', $comment->user) }}">
        <x-avatar :user="$comment->user" :size="34" />
    </a>

    <div style="min-width:0;flex:1">
        <div class="comment-bubble">
            <div class="cb-head">
                <a class="cb-name" href="{{ route('profile.show', $comment->user) }}">{{ $comment->user->name }}</a>
                @if ($comment->user->designation)
                    <span class="tiny muted">{{ $comment->user->designation }}</span>
                @endif
                <span class="cb-time">{{ $comment->created_at->diffForHumans(null, true) }}</span>
            </div>
            <div class="cb-text">{{ $comment->body }}</div>
        </div>

        <div class="comment-tools">
            @auth
                <button type="button" data-reply-toggle="reply-{{ $comment->id }}">Reply</button>
            @endauth

            @if ($canRemove)
                <form method="POST" action="{{ route('comments.destroy', $comment) }}" style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="button" data-confirm="This comment will be removed."
                            data-confirm-title="Delete comment?" data-confirm-label="Delete">Delete</button>
                </form>
            @endif

            @if ($comment->replies->isNotEmpty())
                <span class="tiny muted">
                    {{ $comment->replies->count() }} {{ \Illuminate\Support\Str::plural('reply', $comment->replies->count()) }}
                </span>
            @endif
        </div>

        @if ($comment->replies->isNotEmpty())
            <div class="comment-replies">
                @foreach ($comment->replies as $reply)
                    <div class="comment">
                        <a href="{{ route('profile.show', $reply->user) }}">
                            <x-avatar :user="$reply->user" :size="28" />
                        </a>
                        <div style="min-width:0;flex:1">
                            <div class="comment-bubble">
                                <div class="cb-head">
                                    <a class="cb-name" href="{{ route('profile.show', $reply->user) }}">{{ $reply->user->name }}</a>
                                    <span class="cb-time">{{ $reply->created_at->diffForHumans(null, true) }}</span>
                                </div>
                                <div class="cb-text">{{ $reply->body }}</div>
                            </div>

                            @if ($me && ($me->id === $reply->user_id || $me->id === $post->user_id))
                                <div class="comment-tools">
                                    <form method="POST" action="{{ route('comments.destroy', $reply) }}" style="display:inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" data-confirm="This reply will be removed."
                                                data-confirm-title="Delete reply?" data-confirm-label="Delete">Delete</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @auth
            <form class="comment-form is-reply" id="reply-{{ $comment->id }}" hidden
                  method="POST" action="{{ route('comments.store', $post) }}">
                @csrf
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
