<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticlePostTest extends TestCase
{
    public function test_user_can_create_article_with_rich_text_and_colored_text(): void
    {
        $user = User::factory()->create();

        $htmlBody = '<h2>Introduction</h2><p>This is a modern article with <span style="color: #2563eb; font-weight: bold;">blue colored text</span>.</p><ul><li>First point</li><li>Second point</li></ul>';

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'type' => 'article',
            'title' => 'My Modern Article',
            'body' => $htmlBody,
        ]);

        $post = Post::where('title', 'My Modern Article')->latest('id')->first();
        $this->assertNotNull($post);
        $this->assertEquals('article', $post->type);
        $this->assertTrue($post->isArticle());

        $rendered = $post->renderedBody();
        $this->assertStringContainsString('color: #2563eb', $rendered);
        $this->assertStringContainsString('<h2>Introduction</h2>', $rendered);
        $this->assertStringContainsString('<li>First point</li>', $rendered);

        $response->assertRedirect(route('posts.show', $post));

        $post->delete();
        $user->delete();
    }

    public function test_user_can_upload_inline_image_for_article(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('article-image.jpg', 800, 600);

        $response = $this->actingAs($user)->postJson(route('posts.upload-image'), [
            'image' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['url']);
    }

    public function test_article_validation_requires_title_and_body(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'type' => 'article',
            'title' => '',
            'body' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'body']);

        $user->delete();
    }

    public function test_article_rendered_body_strips_scripts_and_retains_colors(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'user_id' => $user->id,
            'type' => 'article',
            'title' => 'Security test',
            'body' => '<p style="color: #e11d48">Red text</p><script>alert(1)</script><img src="/storage/posts/pic.jpg" onerror="alert(2)" />',
        ]);

        $rendered = $post->renderedBody();
        $this->assertStringContainsString('color: #e11d48', $rendered);
        $this->assertStringNotContainsString('script', $rendered);
        $this->assertStringNotContainsString('onerror', $rendered);
        $this->assertStringContainsString('<img src="/storage/posts/pic.jpg"', $rendered);

        $post->delete();
        $user->delete();
    }

    public function test_post_show_page_renders_separate_containers_and_reactions_for_article(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'user_id' => $user->id,
            'type' => 'article',
            'title' => 'Container and Reaction Test',
            'body' => '<p>Checking separate white containers and reactions</p>',
        ]);

        $response = $this->actingAs($user)->get(route('posts.show', $post));

        $response->assertOk();
        $response->assertSee('rail-panel');
        $response->assertSee('article-page');
        $response->assertSee('data-react-open', false);
        $response->assertSee('data-react-pop', false);

        $post->delete();
        $user->delete();
    }

    public function test_user_can_react_to_article(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'user_id' => $user->id,
            'type' => 'article',
            'title' => 'Article Reaction Test',
            'body' => '<p>Article body</p>',
        ]);

        $response = $this->actingAs($user)->postJson(route('reactions.toggle', $post), [
            'type' => 'like',
        ]);

        $response->assertOk();
        $response->assertJson([
            'mine' => 'like',
            'total' => 1,
        ]);

        // Toggle again to remove reaction
        $toggleResponse = $this->actingAs($user)->postJson(route('reactions.toggle', $post), [
            'type' => 'like',
        ]);

        $toggleResponse->assertOk();
        $toggleResponse->assertJson([
            'mine' => null,
            'total' => 0,
        ]);

        $post->delete();
        $user->delete();
    }
}
