@extends('layouts.admin')

@section('title', 'Audit log')

@section('content')
    <section class="adm-card">
        <div class="adm-toolbar">
            <form method="GET" action="{{ route('admin.activity') }}" role="search">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search actions" aria-label="Search the audit log">
                <button class="btn btn-ghost btn-sm" type="submit"><x-icon name="search" :size="15" /> Search</button>
            </form>
            <span class="muted small">Every change made from the admin panel, newest first.</span>
        </div>

        <div class="table-wrap">
            <table class="adm-table">
                <thead>
                    <tr><th>When</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="nowrap small" title="{{ $log->created_at }}">{{ $log->created_at->format('j M Y, H:i') }}</td>
                            <td class="nowrap">
                                @if ($log->user)
                                    <a href="{{ route('admin.users.show', $log->user) }}">{{ $log->user->name }}</a>
                                @else
                                    <span class="muted">Deleted admin</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <span class="tag {{ ['danger' => 'tag-danger', 'warn' => 'tag-warn', 'info' => ''][$log->tone()] }}">{{ $log->action }}</span>
                            </td>
                            <td>{{ $log->description }}</td>
                            <td class="nowrap small muted">{{ $log->ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><h3>No admin activity yet</h3></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pager', ['paginator' => $logs])
    </section>
@endsection
