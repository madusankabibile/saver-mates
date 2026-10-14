@extends('layouts.admin')

@section('title', 'Members')

@section('actions')
    <a class="btn btn-ghost btn-sm" href="{{ route('admin.users.export', request()->query()) }}"><x-icon name="download" :size="15" /> Export CSV</a>
    <button class="btn btn-sm" type="button" onclick="document.getElementById('new-member').toggleAttribute('hidden');document.getElementById('nm-name').focus()">
        <x-icon name="plus" :size="15" /> New member
    </button>
@endsection

@php
    $filter = request('filter');
    $tabs = [
        [null, 'All', $counts['all']],
        ['online', 'Online', $counts['online']],
        ['admins', 'Admins', $counts['admins']],
        ['suspended', 'Suspended', $counts['suspended']],
        ['inactive', 'Inactive 30+ days', null],
    ];
@endphp

@section('content')
    <section class="adm-card" id="new-member" @unless ($errors->hasAny(['name', 'username', 'password'])) hidden @endunless>
        <div class="adm-card-head"><h2>Create a member account</h2></div>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="form-row">
                <div class="field">
                    <label for="nm-name">Full name</label>
                    <input type="text" id="nm-name" name="name" value="{{ old('name') }}" required>
                    @error('name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="nm-username">Username</label>
                    <input type="text" id="nm-username" name="username" value="{{ old('username') }}" required>
                    @error('username') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="nm-password">Temporary password</label>
                    <input type="text" id="nm-password" name="password" required minlength="8" autocomplete="off">
                    <p class="hint">At least 8 characters. Share it with the member; they can change it in their settings.</p>
                    @error('password') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="nm-designation">Designation</label>
                    <input type="text" id="nm-designation" name="designation" value="{{ old('designation') }}">
                </div>
            </div>
            <label class="checkline" style="margin-bottom:1rem">
                <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin'))> Make this member an administrator
            </label>
            <button class="btn" type="submit">Create account</button>
        </form>
    </section>

    <section class="adm-card">
        <nav class="adm-tabs" aria-label="Filter members">
            @foreach ($tabs as [$key, $label, $n])
                <a class="{{ $filter === $key ? 'is-active' : '' }}"
                   href="{{ route('admin.users.index', array_filter(['filter' => $key, 'q' => request('q'), 'sort' => request('sort')])) }}">
                    {{ $label }}@if (! is_null($n))<span>{{ $n }}</span>@endif
                </a>
            @endforeach
        </nav>

        <div class="adm-toolbar">
            <form method="GET" action="{{ route('admin.users.index') }}" role="search">
                @if ($filter) <input type="hidden" name="filter" value="{{ $filter }}"> @endif
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Name, username, role or skill" aria-label="Search members">
                <select name="sort" aria-label="Sort" onchange="this.form.submit()">
                    @foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'name' => 'Name A–Z', 'active' => 'Recently active', 'posts' => 'Most posts'] as $k => $v)
                        <option value="{{ $k }}" @selected(request('sort', 'newest') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <button class="btn btn-ghost btn-sm" type="submit"><x-icon name="search" :size="15" /> Search</button>
            </form>
            <span class="muted small">{{ number_format($users->total()) }} {{ \Illuminate\Support\Str::plural('member', $users->total()) }}</span>
        </div>

        <form method="POST" action="{{ route('admin.users.bulk') }}" data-bulk>
            @csrf
            <input type="hidden" name="action" value="">

            <div class="table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th class="check"><input type="checkbox" data-bulk-all aria-label="Select all on this page"></th>
                            <th>Member</th>
                            <th>Status</th>
                            <th class="num">Posts</th>
                            <th class="num">Media</th>
                            <th class="num">Files</th>
                            <th>Joined</th>
                            <th>Last seen</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td class="check">
                                    @unless ($user->is(auth()->user()) || $user->is_admin)
                                        <input type="checkbox" name="ids[]" value="{{ $user->id }}" aria-label="Select {{ $user->name }}">
                                    @endunless
                                </td>
                                <td>
                                    <a class="cell-user" href="{{ route('admin.users.show', $user) }}">
                                        <x-avatar :user="$user" :size="34" :presence="true" />
                                        <span class="cu-body">
                                            <span class="cu-name">{{ $user->name }}</span>
                                            <span class="cu-meta">&#64;{{ $user->username }}{{ $user->designation ? ' · '.$user->designation : '' }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="nowrap">
                                    @if ($user->is_admin) <span class="tag tag-admin">Admin</span> @endif
                                    @if ($user->isSuspended())
                                        <span class="tag tag-danger" title="{{ $user->suspension_reason }}"><span class="dot"></span> Suspended</span>
                                    @elseif ($user->isOnline())
                                        <span class="tag tag-ok"><span class="dot"></span> Online</span>
                                    @else
                                        <span class="tag">Active</span>
                                    @endif
                                </td>
                                <td class="num">{{ $user->posts_count }}</td>
                                <td class="num">{{ $user->movies_count + $user->songs_count }}</td>
                                <td class="num">{{ $user->drive_files_count }}</td>
                                <td class="nowrap small">{{ $user->created_at?->format('j M Y') }}</td>
                                <td class="nowrap small muted">{{ $user->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.users.show', $user) }}">Manage</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="empty"><h3>No members match</h3><p>Try a different search or filter.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bulk-bar" data-bulk-bar hidden>
                <span class="bb-count" data-bulk-count></span>
                <button class="btn btn-ghost btn-sm" type="button" data-bulk-clear>Clear</button>
                <button class="btn btn-ghost btn-sm" type="button" data-bulk-action="unsuspend">Reinstate</button>
                <button class="btn btn-ghost btn-sm" type="button" data-bulk-action="suspend"
                        data-bulk-confirm="Suspend {n} members? They are signed out immediately and cannot sign in until reinstated."
                        data-bulk-confirm-title="Suspend members">Suspend</button>
                <button class="btn btn-coral btn-sm" type="button" data-bulk-action="delete"
                        data-bulk-confirm="Permanently delete {n} members and everything they posted, uploaded and shared? This cannot be undone."
                        data-bulk-confirm-title="Delete members">Delete</button>
            </div>
        </form>

        @include('partials.pager', ['paginator' => $users])
    </section>
@endsection
