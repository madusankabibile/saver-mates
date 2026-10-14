@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <div class="page-head">
        <div>
            <h1>Notifications</h1>
            <p>
                @if ($unread)
                    {{ $unread }} unread {{ \Illuminate\Support\Str::plural('notification', $unread) }}
                @else
                    You are all caught up
                @endif
            </p>
        </div>
    </div>

    <div>
        @if ($alerts->isEmpty())
            <div class="card card-pad empty">
                <div class="em-icon"><x-icon name="bell" :size="34" /></div>
                <h3>No notifications yet</h3>
                <p>
                    When a colleague reacts to your post, comments on your update or sends you a message,
                    it shows up here.
                </p>
                <a class="btn btn-sm" href="{{ route('feed.index') }}">Go to the newsfeed</a>
            </div>
        @else
            <div class="card" style="overflow:hidden">
                @foreach ($alerts as $alert)
                    <a class="alert-item {{ $alert->read_at ? '' : 'is-unread' }}" href="{{ $alert->url() }}">
                        <x-avatar :user="$alert->actor" :size="42" />

                        <span class="ai-body">
                            <span class="ai-text">
                                <b>{{ $alert->actor->name }}</b> {{ $alert->sentence() }}
                            </span>
                            @if ($alert->preview)
                                <span class="ai-preview">{{ $alert->preview }}</span>
                            @endif
                            <span class="ai-time">{{ $alert->created_at->diffForHumans() }}</span>
                        </span>

                        <span class="alert-icon">{!! $alert->icon() !!}</span>

                        @unless ($alert->read_at)
                            <span class="ai-mark" title="Unread"></span>
                        @endunless
                    </a>
                @endforeach
            </div>

            @include('partials.pager', ['paginator' => $alerts])
        @endif
    </div>
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="bell" :size="18" />
            <h3>Summary</h3>
            @if ($unread)
                <span class="tally">{{ $unread }} unread</span>
            @endif
        </div>

        @if ($unread)
            <form method="POST" action="{{ route('alerts.read') }}">
                @csrf
                <button class="btn btn-sm btn-block" type="submit">
                    <x-icon name="check" :size="15" /> Mark all as read
                </button>
            </form>
        @else
            <p class="small muted">You are all caught up.</p>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="sparkle" :size="18" />
            <h3>What you get notified about</h3>
        </div>
        <p class="small muted">
            Reactions and comments on your posts, replies to your comments, and new private messages.
        </p>
        <a class="btn btn-ghost btn-sm btn-block" href="{{ route('chat.index') }}">
            <x-icon name="chat" :size="15" /> Open messages
        </a>
    </section>
@endsection
