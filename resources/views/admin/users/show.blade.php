@extends('layouts.admin')

@section('title', $user->name)
@section('crumb')<a href="{{ route('admin.users.index') }}">Members</a> /@endsection

@section('actions')
    <a class="btn btn-ghost btn-sm" href="{{ route('profile.show', $user) }}" target="_blank"><x-icon name="eye" :size="15" /> View profile</a>
@endsection

@php
    use App\Models\DriveFile;
    $self = $user->is(auth()->user());
    $stats = [
        ['Posts', $user->posts_count, route('admin.content.index', ['type' => 'posts', 'user' => $user->id])],
        ['Comments', $user->comments_count, route('admin.content.index', ['type' => 'comments', 'user' => $user->id])],
        ['Reactions', $user->reactions_count, null],
        ['Messages sent', $messagesSent, null],
        ['Movies', $user->movies_count, route('admin.content.index', ['type' => 'movies', 'user' => $user->id])],
        ['Songs', $user->songs_count, route('admin.content.index', ['type' => 'songs', 'user' => $user->id])],
        ['Playlists', $user->playlists_count, null],
        ['Drive files', $user->drive_files_count, route('admin.content.index', ['type' => 'files', 'user' => $user->id])],
    ];
@endphp

@section('content')
    <section class="adm-card">
        <div class="member-head">
            <x-avatar :user="$user" :size="72" :presence="true" />
            <div class="mh-body">
                <h2>{{ $user->name }}</h2>
                <div class="muted small">&#64;{{ $user->username }}{{ $user->designation ? ' · '.$user->designation : '' }}</div>
                <div class="tags">
                    @if ($user->is_admin) <span class="tag tag-admin">Administrator</span> @endif
                    @if ($user->isSuspended())
                        <span class="tag tag-danger"><span class="dot"></span> Suspended {{ $user->suspended_at->diffForHumans() }}</span>
                    @else
                        <span class="tag tag-ok"><span class="dot"></span> {{ $user->lastSeenLabel() }}</span>
                    @endif
                    <span class="tag">Joined {{ $user->created_at?->format('j M Y') }}</span>
                    <span class="tag">{{ DriveFile::humanSize($storage) }} stored</span>
                </div>
            </div>
        </div>

        @if ($user->isSuspended() && $user->suspension_reason)
            <div class="notice" style="margin:1rem 0 0">Suspension reason: {{ $user->suspension_reason }}</div>
        @endif

        <div class="mini-stats">
            @foreach ($stats as [$label, $n, $href])
                <div>
                    <b>@if ($href && $n)<a href="{{ $href }}">{{ number_format($n) }}</a>@else{{ number_format($n) }}@endif</b>
                    <span>{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="adm-grid adm-grid-main adm-section">
        <div>
            <section class="adm-card">
                <div class="adm-card-head"><h2>Account details</h2></div>
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf
                    <div class="form-row">
                        <div class="field">
                            <label for="u-name">Full name</label>
                            <input type="text" id="u-name" name="name" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="field">
                            <label for="u-username">Username</label>
                            <input type="text" id="u-username" name="username" value="{{ old('username', $user->username) }}" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="u-designation">Designation</label>
                        <input type="text" id="u-designation" name="designation" value="{{ old('designation', $user->designation) }}">
                    </div>
                    <div class="field">
                        <label for="u-bio">Bio</label>
                        <textarea id="u-bio" name="bio" rows="3">{{ old('bio', $user->bio) }}</textarea>
                    </div>

                    <div class="switch-row">
                        <div class="sr-body">
                            <b>Administrator</b>
                            <span>Admins can open this panel, manage every member and remove any content.</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin))>
                            <i></i>
                        </label>
                    </div>

                    <button class="btn" type="submit" style="margin-top:.5rem">Save changes</button>
                </form>
            </section>

            <section class="adm-card">
                <div class="adm-card-head">
                    <h2>Recent posts</h2>
                    @if ($user->posts_count)
                        <a class="small" href="{{ route('admin.content.index', ['type' => 'posts', 'user' => $user->id]) }}">All {{ $user->posts_count }}</a>
                    @endif
                </div>
                <ul class="adm-list">
                    @forelse ($posts as $post)
                        <li>
                            <div class="al-body">
                                <a class="al-title" href="{{ route('posts.show', $post) }}" target="_blank">{{ \Illuminate\Support\Str::limit($post->displayTitle(), 90) }}</a>
                                <span class="al-meta">{{ $post->created_at->diffForHumans() }} &middot; {{ $post->reactions_count }} reactions &middot; {{ $post->comments_count }} comments</span>
                            </div>
                            <button class="mini-btn" type="button" title="Delete post"
                                    data-confirm="Delete this post and its images, reactions and comments?"
                                    data-action="{{ route('admin.content.destroy', ['type' => 'posts', 'id' => $post->id]) }}">
                                <x-icon name="trash" :size="15" />
                            </button>
                        </li>
                    @empty
                        <li class="muted small">No posts.</li>
                    @endforelse
                </ul>
            </section>

            @if ($movies->isNotEmpty() || $songs->isNotEmpty() || $files->isNotEmpty())
                <section class="adm-card">
                    <div class="adm-card-head"><h2>Uploads</h2></div>
                    <ul class="adm-list">
                        @foreach ($movies as $m)
                            <li>
                                <x-icon name="film" :size="17" />
                                <a class="al-body" href="{{ route('movies.show', $m) }}" target="_blank"><span class="al-title">{{ $m->title }}</span></a>
                                <span class="al-meta">{{ DriveFile::humanSize($m->video_size) }}</span>
                            </li>
                        @endforeach
                        @foreach ($songs as $s)
                            <li>
                                <x-icon name="music" :size="17" />
                                <span class="al-body"><span class="al-title">{{ $s->title }}</span></span>
                                <span class="al-meta">{{ DriveFile::humanSize($s->size) }}</span>
                            </li>
                        @endforeach
                        @foreach ($files as $f)
                            <li>
                                <x-icon name="{{ $f->is_public ? 'globe' : 'lock' }}" :size="17" />
                                <span class="al-body"><span class="al-title">{{ $f->name }}</span></span>
                                <span class="al-meta">{{ $f->readableSize() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div>
            <section class="adm-card">
                <div class="adm-card-head"><h2>Reset password</h2></div>
                <form method="POST" action="{{ route('admin.users.password', $user) }}">
                    @csrf
                    <div class="field">
                        <label for="pw">New password</label>
                        <input type="password" id="pw" name="password" required minlength="8" autocomplete="new-password">
                        @error('password') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label for="pw2">Confirm new password</label>
                        <input type="password" id="pw2" name="password_confirmation" required autocomplete="new-password">
                    </div>
                    <p class="hint" style="margin:-.3rem 0 .9rem">Also signs them out of "keep me signed in" sessions.</p>
                    <button class="btn btn-ghost" type="submit"><x-icon name="lock" :size="15" /> Set password</button>
                </form>
            </section>

            @unless ($self)
                <section class="adm-card">
                    <div class="adm-card-head"><h2>Access</h2></div>
                    @if ($user->isSuspended())
                        <p class="small muted" style="margin-bottom:.9rem">This member cannot sign in. Reinstating restores access immediately.</p>
                        <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">
                            @csrf
                            <button class="btn" type="submit"><x-icon name="check" :size="15" /> Reinstate member</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                            @csrf
                            <div class="field">
                                <label for="reason">Reason <span class="muted">(shown to them at sign-in)</span></label>
                                <input type="text" id="reason" name="reason" maxlength="200" placeholder="e.g. Repeated off-topic posts">
                            </div>
                            <button class="btn btn-ghost" type="submit"
                                    data-confirm="{{ $user->name }} will be signed out right away and unable to sign in until reinstated."
                                    data-confirm-title="Suspend this member?" data-confirm-label="Suspend">
                                <x-icon name="shield" :size="15" /> Suspend member
                            </button>
                        </form>
                    @endif
                </section>

                <section class="adm-card danger-zone">
                    <div class="adm-card-head"><h2>Delete account</h2></div>
                    <p class="small muted" style="margin-bottom:.9rem">
                        Permanently removes {{ $user->name }} with all their posts, comments, chats, movies, songs, playlists and drive files.
                    </p>
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-coral" type="submit"
                                data-confirm="Delete {{ $user->name }} and everything they shared? This cannot be undone."
                                data-confirm-title="Delete this account?" data-confirm-label="Delete account">
                            <x-icon name="trash" :size="15" /> Delete account
                        </button>
                    </form>
                </section>
            @endunless
        </div>
    </div>
@endsection
