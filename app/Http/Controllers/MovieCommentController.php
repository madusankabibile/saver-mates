<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Movie;
use App\Models\MovieComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Comments are posted and removed with fetch, so writing one never reloads the
 * page and interrupts the movie. Responses carry the rendered comment HTML.
 */
class MovieCommentController extends Controller
{
    public function store(Request $request, Movie $movie): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = null;

        if (! empty($data['parent_id'])) {
            $parent = MovieComment::where('movie_id', $movie->id)->find($data['parent_id']);

            // Two levels only: replying to a reply attaches to its root comment.
            if ($parent?->parent_id) {
                $parent = $parent->parent;
            }
        }

        $comment = MovieComment::create([
            'movie_id' => $movie->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'body' => trim($data['body']),
        ])->load(['user', 'movie', 'replies']);

        $preview = Str::limit($comment->body, 70);

        Alert::record([
            'user_id' => $parent ? $parent->user_id : $movie->user_id,
            'actor_id' => $request->user()->id,
            'type' => $parent ? 'movie_reply' : 'movie_comment',
            'movie_id' => $movie->id,
            'preview' => $preview,
        ]);

        // A reply in someone else's thread still lets the uploader know.
        if ($parent && $parent->user_id !== $movie->user_id) {
            Alert::record([
                'user_id' => $movie->user_id,
                'actor_id' => $request->user()->id,
                'type' => 'movie_comment',
                'movie_id' => $movie->id,
                'preview' => $preview,
            ]);
        }

        return response()->json([
            'parent_id' => $parent?->id,
            'count' => $movie->allComments()->count(),
            'html' => view($parent ? 'movies.partials.reply' : 'movies.partials.comment', [
                'comment' => $comment,
                'movie' => $movie,
            ])->render(),
        ]);
    }

    public function destroy(Request $request, MovieComment $comment): JsonResponse
    {
        $comment->load('movie');
        abort_unless($comment->removableBy($request->user()), 403);

        $movie = $comment->movie;
        $comment->delete(); // replies cascade

        return response()->json(['count' => $movie->allComments()->count()]);
    }
}
