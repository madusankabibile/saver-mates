{{-- Group details: name, notifications, members and admin actions.
     Opened by [data-group-panel-open]; closed by the backdrop, X or Escape. --}}
@php
    use App\Models\ConversationMember;
    $amAdmin = $membership?->isAdmin();
    $adminCount = $conversation->members->where('role', 'admin')->count();
@endphp

<div class="modal-backdrop group-panel" data-group-panel hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="gp-title" style="max-width:560px">
        <div class="modal-head">
            @include('chat.partials.group-avatar', ['group' => $conversation, 'size' => 40])
            <h3 id="gp-title">{{ $conversation->name }}</h3>
            <button class="mini-btn" type="button" data-group-panel-close aria-label="Close"><x-icon name="close" :size="16" /></button>
        </div>

        <div class="modal-body">
            @if ($amAdmin)
                <form class="gp-section" method="POST" action="{{ route('chat.groups.update', $conversation) }}">
                    @csrf
                    <label for="gp-name" class="gp-label">Group name</label>
                    <div class="gp-inline">
                        <input type="text" id="gp-name" name="name" value="{{ $conversation->name }}" maxlength="80" required>
                        <button class="btn btn-ghost btn-sm" type="submit">Rename</button>
                    </div>
                </form>
            @endif

            <div class="gp-section">
                <div class="gp-label">Notifications</div>
                <form method="POST" action="{{ route('chat.groups.mute', $conversation) }}" class="gp-mute">
                    @csrf
                    @if ($membership->isMuted())
                        <span class="tag-muted"><x-icon name="bell-off" :size="14" /> {{ $membership->muteLabel() }}</span>
                        <button class="btn btn-ghost btn-sm" type="submit" name="for" value="off"><x-icon name="bell" :size="14" /> Turn notifications on</button>
                    @else
                        <span class="small muted">Mute for</span>
                        @foreach (ConversationMember::MUTE_OPTIONS as $value => $label)
                            <button class="btn btn-ghost btn-sm" type="submit" name="for" value="{{ $value }}">{{ $value === 'forever' ? 'Until I unmute' : $label }}</button>
                        @endforeach
                    @endif
                </form>
                <p class="hint" style="margin-top:.45rem">A muted group does not count towards your unread badge or ring the notification bell. Messages still arrive here.</p>
            </div>

            <div class="gp-section">
                <div class="gp-label gp-label-row">
                    <span>{{ $conversation->members->count() }} {{ \Illuminate\Support\Str::plural('member', $conversation->members->count()) }}</span>
                    <button class="btn btn-sm" type="button" data-add-members="{{ route('chat.groups.members.add', $conversation) }}" data-group="{{ $conversation->id }}">
                        <x-icon name="users-plus" :size="15" /> Add people
                    </button>
                </div>

                <ul class="gp-members">
                    @foreach ($conversation->members as $member)
                        @continue(! $member->user)
                        @php $isMe = $member->user_id === auth()->id(); @endphp
                        <li>
                            <x-avatar :user="$member->user" :size="34" :presence="true" />
                            <a class="gp-who" href="{{ route('profile.show', $member->user) }}">
                                <span class="gp-name">{{ $member->user->name }}{{ $isMe ? ' (you)' : '' }}</span>
                                <span class="gp-meta">{{ $member->user->designation ?: '@'.$member->user->username }}</span>
                            </a>
                            @if ($member->isAdmin()) <span class="gp-admin">Admin</span> @endif

                            @if ($amAdmin && ! $isMe)
                                <div class="dropdown" data-dropdown="member-{{ $member->id }}">
                                    <button class="mini-btn" type="button" data-dropdown-toggle aria-expanded="false" aria-label="Options for {{ $member->user->name }}"><x-icon name="more" :size="16" /></button>
                                    <div class="dropdown-panel gp-menu" data-dropdown-panel hidden>
                                        <form method="POST" action="{{ route('chat.groups.members.role', [$conversation, $member->user]) }}">
                                            @csrf
                                            <input type="hidden" name="role" value="{{ $member->isAdmin() ? 'member' : 'admin' }}">
                                            <button class="menu-link gp-menu-btn" type="submit" @disabled($member->isAdmin() && $adminCount <= 1)>
                                                <span class="mi"><x-icon name="shield" :size="16" /></span>
                                                {{ $member->isAdmin() ? 'Remove as admin' : 'Make admin' }}
                                            </button>
                                        </form>
                                        <button class="menu-link gp-menu-btn danger" type="button"
                                                data-confirm="{{ $member->user->name }} will be removed from the group."
                                                data-confirm-title="Remove member?" data-confirm-label="Remove"
                                                data-action="{{ route('chat.groups.members.remove', [$conversation, $member->user]) }}">
                                            <span class="mi"><x-icon name="close" :size="16" /></span> Remove from group
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="modal-foot" style="justify-content:space-between">
            <button class="btn btn-ghost btn-sm" type="button"
                    data-confirm="You will stop receiving messages from this group. Someone will need to add you back to rejoin."
                    data-confirm-title="Leave the group?" data-confirm-label="Leave"
                    data-action="{{ route('chat.groups.leave', $conversation) }}" data-method="POST">
                <x-icon name="logout" :size="15" /> Leave group
            </button>
            @if ($amAdmin)
                <button class="btn btn-coral btn-sm" type="button"
                        data-confirm="The group and all its messages and files are deleted for every member."
                        data-confirm-title="Delete this group?" data-confirm-label="Delete group"
                        data-action="{{ route('chat.groups.destroy', $conversation) }}">
                    <x-icon name="trash" :size="15" /> Delete group
                </button>
            @endif
        </div>
    </div>
</div>
