{{-- Right column, below the page panel: recently active members and a link to
     the full directory. Navigation lives in the left panel (tab bar on small screens). --}}
<section class="members" aria-label="Recent members">
    <div class="members-panel">
        <div class="panel-head">
            <x-icon name="people" :size="18" />
            <h3>Recent members</h3>
        </div>

        <div class="members-list">
            @forelse ($recentMembers as $member)
                <a class="person-row" href="{{ route('profile.show', $member) }}">
                    <x-avatar :user="$member" :size="34" :presence="true" />
                    <span class="pr-body">
                        <span class="pr-name">{{ $member->name }}</span>
                        <span class="pr-meta">
                            {{ $member->isOnline() ? 'Online now' : ($member->designation ?: $member->lastSeenLabel()) }}
                        </span>
                    </span>
                </a>
            @empty
                <p class="small muted" style="padding:.4rem .35rem">No other members yet.</p>
            @endforelse
        </div>

        <a class="btn btn-ghost btn-sm btn-block" href="{{ route('directory.index') }}" style="margin-top:.75rem">
            Browse all
        </a>
    </div>
</section>
