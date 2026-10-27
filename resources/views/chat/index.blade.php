@extends('layouts.app')

@php
    $isGroup = $conversation?->is_group;
    $open = $conversation && ($partner || $isGroup);
@endphp

@section('title', $open ? ($isGroup ? $conversation->name : 'Chat with '.$partner->name) : 'Messages')

@section('shell-class', 'shell-chat'.($conversation ? ' has-open' : ''))
@section('main-class', 'main-panel-flush')

@section('content')
    {{-- Message pane ------------------------------------------------- --}}
    @if ($open)
        <section class="chat-pane" data-chat
                 data-conversation="{{ $conversation->id }}"
                 data-last-id="{{ $messages->max('id') ?? 0 }}"
                 data-since="{{ now()->subSecond()->toIso8601String() }}">

            <header class="chat-head">
                <a class="btn btn-ghost btn-sm" href="{{ route('chat.index') }}"
                   style="display:none" aria-label="Back to conversations">
                    <x-icon name="back" :size="15" />
                </a>

                @if ($isGroup)
                    <button class="ch-group-btn" type="button" data-group-panel-open title="Group details">
                        @include('chat.partials.group-avatar', ['group' => $conversation, 'size' => 42])
                    </button>

                    <div class="ch-body">
                        <button class="ch-name ch-name-btn" type="button" data-group-panel-open>{{ $conversation->name }}</button>
                        <div class="ch-status" data-partner-status>
                            {{ $conversation->members->count() }} {{ \Illuminate\Support\Str::plural('member', $conversation->members->count()) }}
                        </div>
                    </div>

                    @if ($membership->isMuted())
                        <span class="tag-muted" title="{{ $membership->muteLabel() }}"><x-icon name="bell-off" :size="14" /> Muted</span>
                    @endif

                    <button class="mini-btn" type="button" data-group-panel-open title="Members, notifications and settings">
                        <x-icon name="people" :size="16" />
                    </button>
                @else
                    <a href="{{ route('profile.show', $partner) }}" data-partner-avatar
                       class="avatar a-42 {{ $partner->isOnline() ? 'is-online' : '' }}">
                        <img src="{{ $partner->avatarUrl() }}" alt="{{ $partner->name }}">
                        <i class="presence"></i>
                    </a>

                    <div class="ch-body">
                        <a class="ch-name" href="{{ route('profile.show', $partner) }}">{{ $partner->name }}</a>
                        <div class="ch-status {{ $partner->isOnline() ? 'is-online' : '' }}" data-partner-status>
                            {{ $partner->lastSeenLabel() }}
                        </div>
                    </div>

                    <a class="mini-btn" href="{{ route('profile.show', $partner) }}" title="View profile">
                        <x-icon name="user" :size="16" />
                    </a>
                @endif
            </header>

            <div class="chat-scroll" data-chat-scroll>
                @php $lastDay = null; @endphp

                @forelse ($messages as $message)
                    @if ($message->created_at->toDateString() !== $lastDay)
                        @php $lastDay = $message->created_at->toDateString(); @endphp
                        <div class="day-divider">
                            @if ($message->created_at->isToday())
                                Today
                            @elseif ($message->created_at->isYesterday())
                                Yesterday
                            @else
                                {{ $message->created_at->format('j M Y') }}
                            @endif
                        </div>
                    @endif

                    @include('chat.partials.bubble', ['message' => $message, 'me' => auth()->user(), 'group' => $isGroup, 'readers' => $readers])
                @empty
                    <div class="empty" style="margin:auto">
                        <div class="em-icon"><x-icon name="send" :size="30" /></div>
                        <h3 style="font-size:1.05rem">Say hello to {{ \Illuminate\Support\Str::of($partner->name)->explode(' ')->first() }}</h3>
                        <p class="small">
                            Messages here are private between the two of you. You can attach files from
                            your PC or straight from your SaverMate Drive.
                        </p>
                    </div>
                @endforelse
            </div>

            <form class="chat-composer" method="POST" action="{{ route('chat.send', $conversation) }}"
                  enctype="multipart/form-data" data-chat-form>
                @csrf

                <div class="pending-attachments" data-pending hidden></div>

                <div class="chat-composer-row">
                    <label class="chat-tool" for="chat-files" title="Upload from this computer">
                        <x-icon name="clip" :size="18" />
                        <span class="tiny" style="position:absolute;left:-9999px">Upload from device</span>
                    </label>
                    <input id="chat-files" type="file" name="attachments[]" multiple hidden data-chat-files>

                    <button class="chat-tool" type="button" data-drive-picker title="Attach from your SaverMate Drive">
                        <x-icon name="cloud" :size="18" />
                    </button>

                    <textarea name="body" rows="1" maxlength="4000"
                              placeholder="{{ $isGroup ? 'Message '.$conversation->name : 'Write a message' }}" data-chat-text></textarea>

                    <button class="chat-send" type="submit" aria-label="Send message">
                        <x-icon name="send" :size="18" />
                    </button>
                </div>
            </form>
        </section>

        @if ($isGroup)
            @include('chat.partials.group-panel')
        @endif
    @else
        <section class="chat-pane">
            <div class="empty" style="margin:auto">
                <div class="em-icon"><x-icon name="chat" :size="36" /></div>
                <h3>Pick a conversation</h3>
                <p>
                    Choose a conversation from the list, start a private chat with a colleague, or
                    create a group for your team. Files can come from your PC or your cloud drive.
                </p>
                <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap">
                    <a class="btn btn-ghost" href="{{ route('directory.index') }}">
                        <x-icon name="people" :size="16" /> Browse colleagues
                    </a>
                    <button class="btn" type="button" data-new-group="{{ route('chat.groups.store') }}">
                        <x-icon name="users-plus" :size="16" /> New group
                    </button>
                </div>
            </div>
        </section>
    @endif
@endsection

@section('left-label', 'Conversations')

@section('left')
    <div class="thread-list">
        <div class="thread-list-head">
            <div class="tl-title">
                <div>
                    <h2>Messages</h2>
                    <p class="small muted" style="margin-top:.1rem">
                        {{ $threads->count() ? $threads->count().' '.\Illuminate\Support\Str::plural('conversation', $threads->count()) : 'No conversations yet' }}
                    </p>
                </div>
                <div class="tl-actions">
                    <button class="btn btn-ghost btn-sm" type="button" data-new-group="{{ route('chat.groups.store') }}" title="Create a group chat">
                        <x-icon name="users-plus" :size="15" /> Group
                    </button>
                    <a class="btn btn-sm" href="{{ route('directory.index') }}" title="Start a chat with a colleague">
                        <x-icon name="plus" :size="15" /> Chat
                    </a>
                </div>
            </div>

            @if ($threads->count() > 1)
                <div class="tl-search">
                    <x-icon name="search" :size="16" />
                    <input type="search" placeholder="Search conversations" aria-label="Search conversations" data-thread-filter>
                </div>
            @endif
        </div>

        <div class="thread-scroll" data-thread-scroll>
            @forelse ($threads as $thread)
                @php
                    $partnerUser = $thread->partner;
                    $latest = $thread->latestMessage;
                    $mine = $latest && $latest->sender_id === auth()->id();
                @endphp
                @continue(! $thread->is_group && ! $partnerUser)

                <a class="thread-item {{ $conversation && $conversation->id === $thread->id ? 'is-active' : '' }} {{ $thread->unread ? 'has-unread' : '' }} {{ $thread->muted ? 'is-muted' : '' }}"
                   href="{{ route('chat.show', $thread) }}"
                   data-thread-name="{{ mb_strtolower($thread->is_group ? $thread->name : $partnerUser->name) }}">
                    @if ($thread->is_group)
                        @include('chat.partials.group-avatar', ['group' => $thread, 'size' => 44])
                    @else
                        <x-avatar :user="$partnerUser" :size="44" :presence="true" />
                    @endif

                    <span class="ti-body">
                        <span class="ti-top">
                            <span class="ti-name">
                                {{ $thread->is_group ? $thread->name : $partnerUser->name }}
                                @if ($thread->muted)
                                    <span class="ti-muted" title="Notifications muted"><x-icon name="bell-off" :size="13" /></span>
                                @endif
                            </span>
                            @if ($thread->last_message_at)
                                <span class="ti-time">{{ $thread->last_message_at->diffForHumans(null, true) }}</span>
                            @endif
                        </span>
                        <span class="ti-bottom">
                            <span class="ti-last">
                                @if ($latest)
                                    @if ($latest->isEvent())
                                        {{ $mine ? 'You' : $latest->sender?->name }} {{ \Illuminate\Support\Str::limit($latest->body, 50) }}
                                    @else
                                        @if ($mine)You: @elseif ($thread->is_group){{ \Illuminate\Support\Str::of($latest->sender?->name)->explode(' ')->first() }}: @endif
                                        {{ $latest->isRemoved() ? 'Message deleted' : \Illuminate\Support\Str::limit($latest->body ?: 'Sent a file', 60) }}
                                    @endif
                                @else
                                    No messages yet
                                @endif
                            </span>
                            @if ($thread->unread)
                                <span class="unread-dot">{{ $thread->unread }}</span>
                            @endif
                        </span>
                    </span>
                </a>
            @empty
                <div class="empty" style="padding:2rem 1rem">
                    <div class="em-icon"><x-icon name="chat" :size="30" /></div>
                    <h3 style="font-size:1rem">No conversations yet</h3>
                    <p class="small">Pick a colleague from the directory to start a private chat, or create a group.</p>
                    <a class="btn btn-sm" href="{{ route('directory.index') }}">Browse colleagues</a>
                </div>
            @endforelse

            <p class="small muted center" data-thread-none hidden style="padding:1rem">No conversation matches.</p>
        </div>
    </div>
@endsection
