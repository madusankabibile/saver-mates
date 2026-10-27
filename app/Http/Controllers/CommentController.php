<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        $parent = null;

        if (! empty($data['parent_id'])) {
            $parent = Comment::find($data['parent_id']);

            // Keep threads two levels deep: replies always attach to a root comment.
            if ($parent && $parent->post_id !== $post->id) {
                $parent = null;
            }

            if ($parent && $parent->parent_id) {
                $parent = $parent->parent;
            }
        }

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'body' => $data['body'],
        ]);

        Alert::record([
            'user_id' => $parent ? $parent->user_id : $post->user_id,
            'actor_id' => $request->user()->id,
            'type' => $parent ? 'reply' : 'comment',
            'post_id' => $post->id,
            'comment_id' => $comment->id,
            'preview' => \Illuminate\Support\Str::limit($data['body'], 70),
        ]);

        // A reply on someone else's thread should still ping the post author.
        if ($parent && $parent->user_id !== $post->user_id) {
            Alert::record([
                'user_id' => $post->user_id,
                'actor_id' => $request->user()->id,
                'type' => 'comment',
                'post_id' => $post->id,
                'comment_id' => $comment->id,
                'preview' => \Illuminate\Support\Str::limit($data['body'], 70),
            ]);
        }

        return back()->with('status', 'Comment added.');
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless(
            $comment->user_id === $request->user()->id || $comment->post->user_id === $request->user()->id,
            403
        );

        $comment->delete();

        return back()->with('status', 'Comment removed.');
    }
}
