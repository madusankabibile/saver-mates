<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use Tests\TestCase;

/**
 * Runs against the real database, so every member it creates is deleted
 * afterwards; deleting the members also removes the groups they emptied.
 */
class GroupChatTest extends TestCase
{
    protected array $users = [];

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            User::find($user->id)?->deleteWithFiles();
        }

        parent::tearDown();
    }

    protected function member(): User
    {
        return $this->users[] = User::factory()->create();
    }

    /** A group run by $owner with the given members. */
    protected function group(User $owner, array $members, string $name = 'Ward 4'): Conversation
    {
        $this->actingAs($owner)->post('/chat/groups', [
            'name' => $name,
            'members' => collect($members)->pluck('id')->all(),
        ])->assertRedirect();

        return Conversation::where('is_group', true)->where('created_by', $owner->id)->latest('id')->firstOrFail();
    }

    protected function send(User $from, Conversation $group, string $body): void
    {
        $this->actingAs($from)->postJson("/chat/{$group->id}/messages", ['body' => $body])->assertOk();
    }

    protected function unread(User $user): int
    {
        return $this->actingAs($user)->getJson('/chat/unread')->json('count');
    }

    public function test_creating_a_group_adds_everyone_and_tells_them(): void
    {
        [$owner, $a, $b] = [$this->member(), $this->member(), $this->member()];

        $group = $this->group($owner, [$a, $b]);

        $this->assertSame('Ward 4', $group->name);
        $this->assertEqualsCanonicalizing([$owner->id, $a->id, $b->id], $group->members()->pluck('user_id')->all());
        $this->assertTrue($group->isAdmin($owner));
        $this->assertFalse($group->isAdmin($a));

        // "created the group" and "added A and B" lines, which are not unread messages.
        $this->assertSame(2, $group->messages()->whereNotNull('event')->count());
        $this->assertSame(0, $this->unread($a));

        $this->assertTrue(Alert::where('user_id', $a->id)->where('type', 'group_added')->exists());
        $this->assertFalse(Alert::where('user_id', $owner->id)->where('type', 'group_added')->exists());

        $this->actingAs($a)->get("/chat/{$group->id}")->assertOk()->assertSee('Ward 4')->assertSee('created the group');
        $this->actingAs($a)->get('/chat')->assertOk()->assertSee('Ward 4');
    }

    public function test_a_group_needs_a_name_and_people(): void
    {
        $owner = $this->member();

        $this->actingAs($owner)->post('/chat/groups', ['name' => '', 'members' => []])
            ->assertSessionHasErrors(['name', 'members']);

        $this->actingAs($owner)->post('/chat/groups', ['name' => 'Just me', 'members' => [$owner->id]])
            ->assertSessionHasErrors('members');
    }

    public function test_outsiders_cannot_read_or_post(): void
    {
        [$owner, $a, $outsider] = [$this->member(), $this->member(), $this->member()];
        $group = $this->group($owner, [$a]);

        $this->actingAs($outsider)->get("/chat/{$group->id}")->assertForbidden();
        $this->actingAs($outsider)->getJson("/chat/{$group->id}/poll")->assertForbidden();
        $this->actingAs($outsider)->postJson("/chat/{$group->id}/messages", ['body' => 'hi'])->assertForbidden();
    }

    public function test_messages_reach_every_member_and_are_read_per_member(): void
    {
        [$owner, $a, $b] = [$this->member(), $this->member(), $this->member()];
        $group = $this->group($owner, [$a, $b]);

        $this->send($owner, $group, 'Morning all');
        $this->send($owner, $group, 'Meeting at 10');

        $this->assertSame(2, $this->unread($a));
        $this->assertSame(2, $this->unread($b));
        $this->assertSame(0, $this->unread($owner));

        // A's reading does not mark it read for B.
        $this->actingAs($a)->get("/chat/{$group->id}")->assertOk();
        $this->assertSame(0, $this->unread($a));
        $this->assertSame(2, $this->unread($b));

        // Poll shows the sender's name and group status.
        $poll = $this->actingAs($b)->getJson("/chat/{$group->id}/poll?after=0")->assertOk();
        $this->assertStringContainsString($owner->name, implode('', $poll->json('html')));
        $this->assertStringContainsString('3 members', $poll->json('partner_status'));
    }

    public function test_a_busy_group_keeps_one_waiting_alert_per_member(): void
    {
        [$owner, $a] = [$this->member(), $this->member()];
        $group = $this->group($owner, [$a]);

        $this->send($owner, $group, 'One');
        $this->send($owner, $group, 'Two');
        $this->send($owner, $group, 'Three');

        $alerts = Alert::where('user_id', $a->id)->where('type', 'group_message')->whereNull('read_at')->get();
        $this->assertCount(1, $alerts);
        $this->assertSame('Three', $alerts->first()->preview);

        // Opening the group clears it.
        $this->actingAs($a)->get("/chat/{$group->id}");
        $this->assertSame(0, Alert::where('user_id', $a->id)->where('conversation_id', $group->id)->whereNull('read_at')->count());
    }

    public function test_muting_silences_the_bell_and_badge_until_unmuted(): void
    {
        [$owner, $a] = [$this->member(), $this->member()];
        $group = $this->group($owner, [$a]);

        $this->actingAs($a)->post("/chat/{$group->id}/mute", ['for' => '8'])->assertRedirect();
        $member = $group->membershipFor($a);
        $this->assertTrue($member->isMuted());
        $this->assertTrue($member->muted_until->between(now()->addHours(7), now()->addHours(9)));

        $this->send($owner, $group, 'Shh');
        $this->assertSame(0, $this->unread($a));
        $this->assertFalse(Alert::where('user_id', $a->id)->where('type', 'group_message')->exists());

        // Still marked as unread inside the chat list itself.
        $this->assertSame(1, $group->fresh()->unreadCountFor($a));

        $this->actingAs($a)->post("/chat/{$group->id}/mute", ['for' => 'forever']);
        $this->assertSame('Muted', $group->membershipFor($a)->muteLabel());

        $this->actingAs($a)->post("/chat/{$group->id}/mute", ['for' => 'off']);
        $this->assertFalse($group->membershipFor($a)->isMuted());
        $this->send($owner, $group, 'Loud');
        $this->assertSame(2, $this->unread($a));
        $this->assertTrue(Alert::where('user_id', $a->id)->where('type', 'group_message')->exists());
    }

    public function test_an_expired_mute_counts_as_unmuted(): void
    {
        [$owner, $a] = [$this->member(), $this->member()];
        $group = $this->group($owner, [$a]);
        $group->membershipFor($a)->update(['muted_until' => now()->subMinute()]);

        $this->send($owner, $group, 'Back');
        $this->assertSame(1, $this->unread($a));
    }

    public function test_members_add_people_but_only_admins_manage_them(): void
    {
        [$owner, $a, $b, $c] = [$this->member(), $this->member(), $this->member(), $this->member()];
        $group = $this->group($owner, [$a]);

        // Any member can add colleagues.
        $this->actingAs($a)->post("/chat/{$group->id}/members", ['members' => [$b->id]])->assertRedirect();
        $this->assertTrue($group->fresh()->includes($b));

        // People search hides who is already in the group.
        $ids = collect($this->actingAs($a)->getJson("/chat/people?group={$group->id}")->json())->pluck('id');
        $this->assertNotContains($b->id, $ids);

        // Non-admins cannot rename, remove or promote.
        $this->actingAs($a)->post("/chat/{$group->id}/group", ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($a)->delete("/chat/{$group->id}/members/{$b->id}")->assertForbidden();
        $this->actingAs($a)->post("/chat/{$group->id}/members/{$b->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->actingAs($a)->delete("/chat/{$group->id}")->assertForbidden();

        // Admins can.
        $this->actingAs($owner)->post("/chat/{$group->id}/group", ['name' => 'Ward 4 nurses'])->assertRedirect();
        $this->assertSame('Ward 4 nurses', $group->fresh()->name);

        $this->actingAs($owner)->post("/chat/{$group->id}/members/{$a->id}/role", ['role' => 'admin']);
        $this->assertTrue($group->fresh()->isAdmin($a));

        $this->actingAs($owner)->delete("/chat/{$group->id}/members/{$b->id}")->assertRedirect();
        $this->assertFalse($group->fresh()->includes($b));
        $this->actingAs($b)->get("/chat/{$group->id}")->assertForbidden();

        $this->assertNotNull($c); // spare member, never added
    }

    public function test_the_last_admin_cannot_step_down(): void
    {
        [$owner, $a] = [$this->member(), $this->member()];
        $group = $this->group($owner, [$a]);

        $this->actingAs($owner)->post("/chat/{$group->id}/members/{$owner->id}/role", ['role' => 'member'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($group->fresh()->isAdmin($owner));
    }

    public function test_leaving_hands_over_admin_and_an_empty_group_is_deleted(): void
    {
        [$owner, $a] = [$this->member(), $this->member()];
        $group = $this->group($owner, [$a]);

        $this->actingAs($owner)->post("/chat/{$group->id}/leave")->assertRedirect(route('chat.index'));
        $this->assertFalse($group->fresh()->includes($owner));
        $this->assertTrue($group->fresh()->isAdmin($a));
        $this->assertTrue($group->messages()->where('event', 'left')->exists());

        $this->actingAs($a)->post("/chat/{$group->id}/leave");
        $this->assertNull(Conversation::find($group->id));
    }

    public function test_deleting_a_member_account_keeps_their_groups_running(): void
    {
        [$owner, $a, $b] = [$this->member(), $this->member(), $this->member()];
        $group = $this->group($owner, [$a, $b]);

        $owner->deleteWithFiles();

        $group = $group->fresh();
        $this->assertNotNull($group);
        $this->assertSame(2, $group->members()->count());
        $this->assertSame(1, $group->members()->where('role', 'admin')->count());
    }

    public function test_admins_can_delete_the_group(): void
    {
        [$owner, $a] = [$this->member(), $this->member()];
        $group = $this->group($owner, [$a]);

        $this->actingAs($owner)->delete("/chat/{$group->id}")->assertRedirect(route('chat.index'));
        $this->assertNull(Conversation::find($group->id));
        $this->assertSame(0, ConversationMember::where('conversation_id', $group->id)->count());
    }

    public function test_private_chat_ticks_turn_read_when_opened(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $dm = Conversation::between($a, $b);

        $sent = $this->actingAs($a)->postJson("/chat/{$dm->id}/messages", ['body' => 'Hello'])->assertOk();
        $this->assertStringContainsString('receipt is-sent', $sent->json('html'));
        $this->assertSame(0, $this->actingAs($a)->getJson("/chat/{$dm->id}/poll?after=0")->json('read_upto'));

        $this->actingAs($b)->get("/chat/{$dm->id}");

        $poll = $this->actingAs($a)->getJson("/chat/{$dm->id}/poll?after={$sent->json('id')}");
        $this->assertSame($sent->json('id'), $poll->json('read_upto'));
        $this->actingAs($a)->get("/chat/{$dm->id}")->assertSee('receipt is-read', false);
    }

    public function test_group_receipts_show_who_read_a_message(): void
    {
        [$owner, $a, $b] = [$this->member(), $this->member(), $this->member()];
        $group = $this->group($owner, [$a, $b]);

        $id = $this->actingAs($owner)->postJson("/chat/{$group->id}/messages", ['body' => 'Agenda'])->json('id');
        $this->actingAs($owner)->get("/chat/{$group->id}")->assertSee('receipt is-sent', false);

        // A reads it: partially read, and the info lists A as read, B as not yet.
        $this->actingAs($a)->get("/chat/{$group->id}");
        $this->actingAs($owner)->get("/chat/{$group->id}")->assertSee('receipt is-partial', false);

        $info = $this->actingAs($owner)->getJson("/chat/{$group->id}/messages/{$id}/info")->assertOk();
        $this->assertSame([$a->name], collect($info->json('readers'))->pluck('name')->all());
        $this->assertSame([$b->name], collect($info->json('waiting'))->pluck('name')->all());

        // Someone added afterwards never saw it, so they do not count either way.
        $late = $this->member();
        $this->actingAs($owner)->post("/chat/{$group->id}/members", ['members' => [$late->id]]);
        $info = $this->actingAs($owner)->getJson("/chat/{$group->id}/messages/{$id}/info");
        $this->assertNotContains($late->name, collect($info->json('readers'))->merge($info->json('waiting'))->pluck('name'));

        // B reads it: read by everyone who was there.
        $this->actingAs($b)->get("/chat/{$group->id}");
        $this->actingAs($owner)->get("/chat/{$group->id}")->assertSee('receipt is-read', false);

        // The poll carries [joined, read] pairs so open windows update their ticks.
        $readers = $this->actingAs($owner)->getJson("/chat/{$group->id}/poll?after={$id}")->json('readers');
        $this->assertCount(3, $readers);

        // Only the sender can look; outsiders cannot at all.
        $this->actingAs($a)->getJson("/chat/{$group->id}/messages/{$id}/info")->assertNotFound();
        $this->actingAs($this->member())->getJson("/chat/{$group->id}/messages/{$id}/info")->assertForbidden();
    }

    public function test_senders_edit_their_messages_and_open_windows_see_it(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $dm = Conversation::between($a, $b);
        $id = $this->actingAs($a)->postJson("/chat/{$dm->id}/messages", ['body' => 'Meeting at 10'])->json('id');
        $since = now()->subSecond()->toIso8601String();

        // Every bubble has the menu; only the sender's carries edit/delete/info.
        $this->actingAs($a)->get("/chat/{$dm->id}")->assertSee('data-msg-menu', false)->assertSee('data-edit-url', false);
        $this->actingAs($b)->get("/chat/{$dm->id}")->assertSee('data-msg-menu', false)->assertDontSee('data-edit-url', false);

        $this->actingAs($b)->postJson("/chat/{$dm->id}/messages/{$id}", ['body' => 'Hacked'])->assertNotFound();

        $edit = $this->actingAs($a)->postJson("/chat/{$dm->id}/messages/{$id}", ['body' => 'Meeting at 11'])->assertOk();
        $this->assertStringContainsString('Meeting at 11', $edit->json('html'));
        $this->assertStringContainsString('edited', $edit->json('html'));
        $this->assertNotNull(\App\Models\Message::find($id)->edited_at);

        // B's open window picks the change up on its next poll.
        $poll = $this->actingAs($b)->getJson("/chat/{$dm->id}/poll?after={$id}&since=".urlencode($since))->assertOk();
        $this->assertSame([$id], collect($poll->json('changed'))->pluck('id')->all());
        $this->assertStringContainsString('Meeting at 11', $poll->json('changed.0.html'));

        // Emptying a text-only message is refused.
        $this->actingAs($a)->postJson("/chat/{$dm->id}/messages/{$id}", ['body' => '  '])->assertStatus(422);
    }

    public function test_senders_delete_their_messages_for_everyone(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $dm = Conversation::between($a, $b);
        $id = $this->actingAs($a)->postJson("/chat/{$dm->id}/messages", ['body' => 'Oops wrong chat'])->json('id');
        $this->assertSame(1, $this->unread($b));

        $this->actingAs($b)->deleteJson("/chat/{$dm->id}/messages/{$id}")->assertNotFound();

        $this->actingAs($a)->deleteJson("/chat/{$dm->id}/messages/{$id}")->assertOk()
            ->assertJsonPath('id', $id);

        $message = \App\Models\Message::find($id);
        $this->assertNotNull($message->removed_at);
        $this->assertNull($message->body);
        $this->assertSame(0, $this->unread($b));

        // The bell no longer quotes the deleted text.
        $this->assertFalse(Alert::where('user_id', $b->id)->where('preview', 'Oops wrong chat')->exists());

        $this->actingAs($b)->get("/chat/{$dm->id}")->assertSee('This message was deleted')->assertDontSee('Oops wrong chat');
        $this->actingAs($b)->get('/chat')->assertSee('Message deleted');

        // A deleted message cannot be edited or inspected.
        $this->actingAs($a)->postJson("/chat/{$dm->id}/messages/{$id}", ['body' => 'Back'])->assertNotFound();
        $this->actingAs($a)->getJson("/chat/{$dm->id}/messages/{$id}/info")->assertNotFound();
    }

    public function test_private_message_info_shows_sent_and_read_times(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $dm = Conversation::between($a, $b);
        $id = $this->actingAs($a)->postJson("/chat/{$dm->id}/messages", ['body' => 'Hi'])->json('id');

        $info = $this->actingAs($a)->getJson("/chat/{$dm->id}/messages/{$id}/info")->assertOk();
        $this->assertFalse($info->json('group'));
        $this->assertNull($info->json('read'));

        $this->actingAs($b)->get("/chat/{$dm->id}");
        $this->assertNotNull($this->actingAs($a)->getJson("/chat/{$dm->id}/messages/{$id}/info")->json('read'));
    }

    public function test_times_are_stored_in_the_app_timezone(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $dm = Conversation::between($a, $b);
        $this->actingAs($a)->postJson("/chat/{$dm->id}/messages", ['body' => 'What time is it?']);

        $stored = \DB::table('messages')->where('conversation_id', $dm->id)->value('created_at');
        $this->assertSame(now()->format('Y-m-d H:i'), substr($stored, 0, 16));
        $this->assertSame(config('app.timezone'), now()->getTimezone()->getName());
    }

    public function test_private_chats_still_work(): void
    {
        [$a, $b] = [$this->member(), $this->member()];

        $this->actingAs($a)->get("/chat/with/{$b->id}")->assertRedirect();
        $dm = Conversation::between($a, $b);
        $this->assertFalse($dm->is_group);

        $this->actingAs($a)->postJson("/chat/{$dm->id}/messages", ['body' => 'Hi'])->assertOk();
        $this->assertSame(1, $this->unread($b));
        $this->actingAs($b)->get("/chat/{$dm->id}")->assertOk()->assertSee('Hi');
        $this->assertSame(0, $this->unread($b));

        // The private chat cannot be managed like a group.
        $this->actingAs($a)->post("/chat/{$dm->id}/mute", ['for' => '1'])->assertNotFound();
        $this->actingAs($a)->delete("/chat/{$dm->id}")->assertNotFound();
    }
}
