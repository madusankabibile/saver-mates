<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DriveFile;
use App\Models\User;
use Tests\TestCase;

class ChatDrivePickerTest extends TestCase
{
    protected array $users = [];

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }

        parent::tearDown();
    }

    protected function member(): User
    {
        return $this->users[] = User::factory()->create();
    }

    protected function file(User $owner, string $name, bool $public): DriveFile
    {
        return DriveFile::create([
            'user_id' => $owner->id, 'name' => $name, 'path' => 'drive/'.uniqid().'.pdf',
            'mime' => 'application/pdf', 'size' => 1000, 'is_public' => $public,
        ]);
    }

    public function test_picker_searches_own_files_and_the_network_drive(): void
    {
        $me = $this->member();
        $colleague = $this->member();
        $tag = 'zq'.uniqid(); // keeps the search to this test's files

        $this->file($me, "{$tag} my private.pdf", false);
        $this->file($me, "{$tag} my public.pdf", true);
        $this->file($colleague, "{$tag} shared report.pdf", true);
        $this->file($colleague, "{$tag} secret notes.pdf", false);

        $data = $this->actingAs($me)->getJson(route('chat.picker', ['q' => $tag]))->assertOk()->json();

        $this->assertEqualsCanonicalizing(["{$tag} my private.pdf", "{$tag} my public.pdf"], array_column($data['mine'], 'name'));
        $this->assertSame(["{$tag} shared report.pdf"], array_column($data['network'], 'name'), 'Only published files of others.');
        $this->assertSame($colleague->name, $data['network'][0]['owner']);

        $mineOnly = $this->actingAs($me)->getJson(route('chat.picker', ['q' => $tag, 'scope' => 'mine']))->json();
        $this->assertEmpty($mineOnly['network']);

        $networkOnly = $this->actingAs($me)->getJson(route('chat.picker', ['q' => $tag, 'scope' => 'network']))->json();
        $this->assertEmpty($networkOnly['mine']);
        $this->assertCount(1, $networkOnly['network']);
    }

    public function test_network_files_can_be_attached_but_others_private_files_cannot(): void
    {
        $me = $this->member();
        $colleague = $this->member();
        $friend = $this->member();

        $shared = $this->file($colleague, 'shared.pdf', true);
        $secret = $this->file($colleague, 'secret.pdf', false);

        $conversation = Conversation::between($me, $friend);

        $this->actingAs($me)->postJson(route('chat.send', $conversation), [
            'drive_files' => [$shared->id, $secret->id],
        ])->assertOk();

        $names = $conversation->messages()->latest('id')->first()->attachments->pluck('name')->all();
        $this->assertSame(['shared.pdf'], $names);
    }
}
