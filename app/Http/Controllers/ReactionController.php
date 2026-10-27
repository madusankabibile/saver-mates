<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Post;
use App\Models\Reaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReactionController extends Controller
{
    /**
     * Add, switch or remove the member's single reaction on a post.
     */
    public function toggle(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Reaction::TYPES))],
        ]);

        $user = $request->user();
        $existing = $post->reactions()->where('user_id', $user->id)->first();

        if ($existing && $existing->type === $data['type']) {
            $existing->delete();
            $mine = null;
        } else {
            $reaction = $post->reactions()->updateOrCreate(
                ['user_id' => $user->id],
                ['type' => $data['type']]
            );

            $mine = $reaction->type;

            Alert::record([
                'user_id' => $post->user_id,
                'actor_id' => $user->id,
                'type' => 'reaction',
                'post_id' => $post->id,
                'preview' => Reaction::label($mine).' on your post',
            ]);
        }

        $post->load('reactions');

        return response()->json([
            'mine' => $mine,
            'total' => $post->reactions->count(),
            'summary' => $post->reactionSummary(),
        ]);
    }
}
