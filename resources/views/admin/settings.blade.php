@extends('layouts.admin')

@section('title', 'Settings & broadcast')

@section('content')
    <div class="adm-grid adm-grid-2">
        <section class="adm-card">
            <div class="adm-card-head"><h2>Site settings</h2></div>

            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf

                <div class="switch-row">
                    <div class="sr-body">
                        <b>Open registration</b>
                        <span>Anyone on the network can create an account from the sign-in popup. When closed, admins create accounts from the Members page.</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="registration_open" value="1" @checked($settings['registration_open'])>
                        <i></i>
                    </label>
                </div>

                <div class="switch-row" style="display:block">
                    <div class="sr-body" style="margin-bottom:.7rem">
                        <b>Announcement banner</b>
                        <span>Shown at the top of every page for members and guests. Leave empty to hide it.</span>
                    </div>
                    <div class="field">
                        <label for="announcement" class="sr-only">Announcement text</label>
                        <textarea id="announcement" name="announcement" rows="2" maxlength="300"
                                  placeholder="The network will be down for maintenance on Saturday from 8 to 10 am.">{{ old('announcement', $settings['announcement']) }}</textarea>
                        @error('announcement') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label for="tone">Style</label>
                        <select id="tone" name="announcement_tone">
                            @foreach (['info' => 'Information (green)', 'warning' => 'Warning (amber)', 'danger' => 'Urgent (red)'] as $k => $v)
                                <option value="{{ $k }}" @selected(old('announcement_tone', $settings['announcement_tone']) === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button class="btn" type="submit">Save settings</button>
            </form>
        </section>

        <section class="adm-card" id="broadcast">
            <div class="adm-card-head"><h2>Send a notification</h2></div>
            <p class="small muted" style="margin-bottom:1rem">
                Delivered to the notification bell of {{ number_format(max(0, $recipients - 1)) }} active
                {{ \Illuminate\Support\Str::plural('member', max(0, $recipients - 1)) }}. Suspended members are skipped.
            </p>

            <form method="POST" action="{{ route('admin.broadcast') }}">
                @csrf
                <div class="field">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="4" maxlength="250" required
                              placeholder="Please update your profile with your new extension number.">{{ old('message') }}</textarea>
                    <p class="hint">Up to 250 characters.</p>
                    @error('message') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <button class="btn" type="submit"
                        data-confirm="Send this notification to every active member? It cannot be recalled."
                        data-confirm-title="Send to everyone?" data-confirm-label="Send now">
                    <x-icon name="send" :size="15" /> Send to everyone
                </button>
            </form>
        </section>
    </div>
@endsection
