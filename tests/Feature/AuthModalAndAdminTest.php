<?php

namespace Tests\Feature;

use App\Models\AdminLog;
use App\Models\Alert;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Runs against the real database (like the other feature tests here), so it
 * creates its own members and removes them, their content and log rows after.
 */
class AuthModalAndAdminTest extends TestCase
{
    protected array $users = [];

    protected array $settings = [];

    /** Fake HTTP responses keyed by host. */
    protected array $http = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->settings = Setting::cached();

        // Registration fetches a random banner; never let tests reach the real API.
        // Requests answer from $this->http by host, or as a 503 (API down).
        Http::preventStrayRequests();
        Http::fake(fn ($request) => $this->http[parse_url($request->url(), PHP_URL_HOST)] ?? Http::response('', 503));
    }

    protected function tearDown(): void
    {
        $ids = collect($this->users)->pluck('id');
        AdminLog::whereIn('user_id', $ids)->delete();

        foreach ($this->users as $user) {
            User::find($user->id)?->deleteWithFiles();
        }

        Setting::put(array_intersect_key($this->settings, Setting::DEFAULTS));

        parent::tearDown();
    }

    protected function member(array $attrs = []): User
    {
        $user = User::factory()->create(['password' => 'secret-pass'] + $attrs);

        if (isset($attrs['is_admin']) || isset($attrs['suspended_at'])) {
            $user->forceFill(array_intersect_key($attrs, array_flip(['is_admin', 'suspended_at'])))->save();
        }

        return $this->users[] = $user;
    }

    /* Sign-in popup ---------------------------------------------------- */

    public function test_login_address_opens_the_popup_over_the_feed(): void
    {
        $this->get('/login')->assertRedirect(route('feed.index'))->assertSessionHas('auth_modal', 'login');

        $this->withSession(['auth_modal' => 'login'])->get('/')
            ->assertOk()
            ->assertSee('data-auth-modal data-start="login"', false);
    }

    public function test_protected_pages_send_guests_to_the_popup_and_back_after_sign_in(): void
    {
        $user = $this->member();

        $this->get('/chat')->assertRedirect(route('login'));

        $this->postJson('/login', ['username' => $user->username, 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJson(['redirect' => url('/chat')]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_popup_login_returns_to_the_page_it_was_opened_on(): void
    {
        $user = $this->member();

        $this->postJson('/login', [
            'username' => $user->username,
            'password' => 'secret-pass',
            'return_to' => url('/movies'),
        ])->assertJson(['redirect' => url('/movies')]);
    }

    public function test_popup_login_ignores_off_site_return_addresses(): void
    {
        $user = $this->member();

        $this->postJson('/login', [
            'username' => $user->username,
            'password' => 'secret-pass',
            'return_to' => 'https://evil.example/phish',
        ])->assertJson(['redirect' => route('feed.index')]);
    }

    public function test_bad_credentials_come_back_as_field_errors(): void
    {
        $user = $this->member();

        $this->postJson('/login', ['username' => $user->username, 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');

        $this->assertGuest();
    }

    public function test_popup_registration_creates_the_account(): void
    {
        Setting::put(['registration_open' => '1']);
        $username = 'modal'.uniqid();

        $this->postJson('/register', [
            'name' => 'Popup Person',
            'username' => $username,
            'password' => 'long-password',
            'password_confirmation' => 'long-password',
        ])->assertOk()->assertJson(['redirect' => route('profile.edit')]);

        $this->users[] = User::where('username', $username)->firstOrFail();
        $this->assertAuthenticated();
    }

    public function test_closed_registration_is_refused(): void
    {
        Setting::put(['registration_open' => '0']);

        $this->postJson('/register', [
            'name' => 'Nope', 'username' => 'nope'.uniqid(),
            'password' => 'long-password', 'password_confirmation' => 'long-password',
        ])->assertStatus(422);

        $this->get('/')->assertDontSee('data-auth-form="register"', false);
    }

    /* Random banner on registration ------------------------------------ */

    protected function fakeWallpaper($image): void
    {
        $this->http = [
            'v2.xxapi.cn' => Http::response(['code' => 200, 'data' => 'https://images.example.test/wall.jpg']),
            'images.example.test' => Http::response($image),
        ];
    }

    protected function jpeg(): string
    {
        $img = imagecreatetruecolor(40, 20);
        ob_start();
        imagejpeg($img);

        return ob_get_clean();
    }

    protected function register(): User
    {
        Setting::put(['registration_open' => '1']);
        $username = 'cover'.uniqid();

        $this->postJson('/register', [
            'name' => 'Cover Person', 'username' => $username,
            'password' => 'long-password', 'password_confirmation' => 'long-password',
        ])->assertOk();

        return $this->users[] = User::where('username', $username)->firstOrFail();
    }

    public function test_new_members_get_a_random_banner(): void
    {
        $this->fakeWallpaper($this->jpeg());

        $user = $this->register();

        $this->assertNotNull($user->cover);
        $this->assertStringStartsWith('covers/', $user->cover);
        Storage::disk('public')->assertExists($user->cover);
    }

    public function test_registration_still_works_when_the_wallpaper_api_is_down(): void
    {
        $user = $this->register(); // setUp fakes every request as a 503

        $this->assertNull($user->cover);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_non_image_response_is_not_saved_as_a_banner(): void
    {
        $this->fakeWallpaper('<html>not an image</html>');

        $this->assertNull($this->register()->cover);
    }

    public function test_admin_created_accounts_get_a_banner_too(): void
    {
        $this->fakeWallpaper($this->jpeg());
        $admin = $this->member(['is_admin' => true]);
        $username = 'made'.uniqid();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Made By Admin', 'username' => $username, 'password' => 'long-password',
        ])->assertRedirect();

        $user = $this->users[] = User::where('username', $username)->firstOrFail();
        $this->assertNotNull($user->cover);
    }

    /* Suspension ------------------------------------------------------- */

    public function test_suspended_members_cannot_sign_in(): void
    {
        $user = $this->member(['suspended_at' => now()]);

        $this->postJson('/login', ['username' => $user->username, 'password' => 'secret-pass'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_suspension_ends_an_open_session(): void
    {
        $user = $this->member();
        $user->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($user)->get('/drive')->assertRedirect(route('feed.index'));
        $this->assertGuest();
    }

    /* Admin panel ------------------------------------------------------ */

    public function test_members_cannot_open_the_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs($this->member())->get('/admin')->assertForbidden();
    }

    public function test_every_admin_page_renders(): void
    {
        $admin = $this->member(['is_admin' => true]);
        $other = $this->member();
        Post::create(['user_id' => $other->id, 'type' => 'standard', 'body' => 'Admin test post']);

        $this->actingAs($admin);

        $this->get('/admin')->assertOk()->assertSee('New members per day');
        $this->get('/admin/users?q='.$other->username)->assertOk()->assertSee($other->name);
        $this->get('/admin/users/'.$other->id)->assertOk();
        $this->get('/admin/settings')->assertOk();
        $this->get('/admin/activity')->assertOk();
        $this->get('/admin/users/export')->assertOk();

        foreach (['posts', 'comments', 'movie-comments', 'movies', 'songs', 'files'] as $type) {
            $this->get('/admin/content/'.$type)->assertOk();
        }
    }

    public function test_admin_can_delete_and_bulk_delete_posts(): void
    {
        $admin = $this->member(['is_admin' => true]);
        $author = $this->member();
        $posts = collect(range(1, 3))->map(fn ($i) => Post::create(['user_id' => $author->id, 'type' => 'standard', 'body' => "Post $i"]));

        $this->actingAs($admin)->delete('/admin/content/posts/'.$posts[0]->id)->assertRedirect();
        $this->assertModelMissing($posts[0]);

        $this->post('/admin/content/posts/bulk-delete', ['ids' => [$posts[1]->id, $posts[2]->id]])->assertRedirect();
        $this->assertSame(0, Post::where('user_id', $author->id)->count());
        $this->assertTrue(AdminLog::where('user_id', $admin->id)->where('action', 'content.bulk-delete-posts')->exists());
    }

    public function test_admin_manages_a_member(): void
    {
        $admin = $this->member(['is_admin' => true]);
        $user = $this->member();
        $this->actingAs($admin);

        $this->post("/admin/users/{$user->id}", ['name' => 'Renamed', 'username' => $user->username, 'is_admin' => '1'])->assertRedirect();
        $this->assertTrue($user->fresh()->is_admin);
        $this->assertSame('Renamed', $user->fresh()->name);

        $this->post("/admin/users/{$user->id}/suspend", ['reason' => 'Testing'])->assertRedirect();
        $this->assertTrue($user->fresh()->isSuspended());

        $this->post("/admin/users/{$user->id}/unsuspend")->assertRedirect();
        $this->assertFalse($user->fresh()->isSuspended());

        $this->post("/admin/users/{$user->id}/password", ['password' => 'brand-new-pw', 'password_confirmation' => 'brand-new-pw']);
        $this->assertTrue(\Hash::check('brand-new-pw', $user->fresh()->password));

        $this->delete("/admin/users/{$user->id}")->assertRedirect(route('admin.users.index'));
        $this->assertModelMissing($user);
    }

    public function test_admins_cannot_suspend_or_delete_themselves(): void
    {
        $admin = $this->member(['is_admin' => true]);
        $this->actingAs($admin);

        $this->post("/admin/users/{$admin->id}/suspend")->assertSessionHasErrors('user');
        $this->delete("/admin/users/{$admin->id}")->assertSessionHasErrors('user');
        $this->assertFalse($admin->fresh()->isSuspended());
    }

    public function test_bulk_actions_skip_admins(): void
    {
        $admin = $this->member(['is_admin' => true]);
        $otherAdmin = $this->member(['is_admin' => true]);
        $user = $this->member();

        $this->actingAs($admin)->post('/admin/users/bulk', [
            'action' => 'suspend', 'ids' => [$admin->id, $otherAdmin->id, $user->id],
        ])->assertRedirect();

        $this->assertTrue($user->fresh()->isSuspended());
        $this->assertFalse($otherAdmin->fresh()->isSuspended());
        $this->assertFalse($admin->fresh()->isSuspended());
    }

    public function test_settings_and_broadcast(): void
    {
        $admin = $this->member(['is_admin' => true]);
        $user = $this->member();
        $this->actingAs($admin);

        $this->post('/admin/settings', [
            'announcement' => 'Maintenance tonight', 'announcement_tone' => 'warning',
        ])->assertRedirect();

        $this->assertFalse(Setting::flag('registration_open')); // unticked switch
        $this->get('/')->assertSee('Maintenance tonight');

        $this->post('/admin/broadcast', ['message' => 'Hello everyone'])->assertRedirect();
        $this->assertTrue(Alert::where('user_id', $user->id)->where('type', 'announcement')->where('preview', 'Hello everyone')->exists());
    }
}
