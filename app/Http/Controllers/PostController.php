<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PostController extends Controller
{
    public function show(Post $post): View
    {
        $post->load(['user', 'images', 'reactions', 'rootComments.user', 'rootComments.replies.user']);
        $post->loadCount('comments');

        return view('feed.show', ['post' => $post]);
    }

    /**
     * Renders Markdown or sanitized rich text HTML for preview.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:100000']]);
        $body = (string) ($data['body'] ?? '');

        $html = preg_match('/<[a-z][\s\S]*>/i', $body)
            ? \App\Support\HtmlSanitizer::clean($body)
            : Str::markdown($body, Post::MARKDOWN);

        return response()->json([
            'html' => $html,
        ]);
    }

    /**
     * Uploads an inline image for rich text articles.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:6144'],
        ]);

        $path = ImageStore::put($request->file('image'), 'posts', 1800, 1800);

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Post::KINDS)],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:100000'],
            'theme' => ['nullable', Rule::in(array_keys(Post::THEMES))],
            'images' => ['nullable', 'array', 'max:4'],
            'images.*' => ['image', 'max:6144'],
        ]);

        $hasImages = $request->hasFile('images');

        if ($data['type'] === 'colored') {
            // A gradient card with no words has nothing to say.
            if (blank($data['body'] ?? null)) {
                throw ValidationException::withMessages(['body' => 'Write something for your colored post.']);
            }

            $data['theme'] = $data['theme'] ?? 'violet';
            $data['title'] = null;
            $hasImages = false;
        } elseif ($data['type'] === 'article') {
            $data['theme'] = null;
            $errors = [];

            if (blank($data['title'] ?? null)) {
                $errors['title'] = 'Give your article a title.';
            }

            $rawText = trim(strip_tags((string) ($data['body'] ?? '')));
            $hasEmbeddedMedia = str_contains((string) ($data['body'] ?? ''), '<img');

            if ($rawText === '' && ! $hasEmbeddedMedia) {
                $errors['body'] = 'An article needs some body text.';
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }
        } else {
            $data['theme'] = null;
            $data['title'] = null;

            if (blank($data['body'] ?? null) && ! $hasImages) {
                throw ValidationException::withMessages(['body' => 'Add a message or attach at least one image.']);
            }
        }

        $post = $request->user()->posts()->create([
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'theme' => $data['theme'],
        ]);

        if ($hasImages) {
            foreach (array_values($request->file('images')) as $index => $image) {
                $post->images()->create([
                    'path' => ImageStore::put($image, 'posts', 1800, 1800),
                    'position' => $index,
                ]);
            }
        }

        // Articles open on their own page so the author sees the full piece;
        // quick posts drop you back into the feed where you were.
        return $post->isArticle()
            ? redirect()->route('posts.show', $post)->with('status', 'Your article is published.')
            : redirect()->route('feed.index')->with('status', 'Your post is live on the newsfeed.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        foreach ($post->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $post->delete();

        return back()->with('status', 'Post deleted.');
    }
}
