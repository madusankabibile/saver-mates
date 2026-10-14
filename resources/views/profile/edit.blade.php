@extends('layouts.app')

@section('title', 'Edit profile')

@section('content')
    <div class="page-head">
        <div>
            <h1>Edit profile</h1>
            <p>This is what colleagues see in the directory and on your posts.</p>
        </div>
        <div class="spacer"></div>
        <a class="btn btn-ghost" href="{{ route('profile.show', $user) }}">
            <x-icon name="eye" :size="16" /> View my profile
        </a>
    </div>

    <form class="card" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
        @csrf

        {{-- Cover banner and avatar, previewed in place --------------- --}}
        <div class="cover-fade">
            <img id="cover-preview" alt="Cover banner"
                 src="{{ $user->coverUrl() ?: asset('img/cover-placeholder.svg') }}"
                 style="display:block;width:100%;height:190px;object-fit:cover;border-radius:var(--r-lg) var(--r-lg) 0 0">

            {{-- Top corner: the bottom of the banner fades to white. --}}
            <label class="btn btn-sm btn-white" for="cover"
                   style="position:absolute;right:1rem;top:1rem;z-index:2;cursor:pointer;box-shadow:0 2px 8px rgba(19,35,29,.18)">
                <x-icon name="image" :size="15" /> Change banner
            </label>
            <input type="file" id="cover" name="cover" accept="image/*" hidden data-image-preview="#cover-preview">
        </div>

        {{-- Positioned so it paints over the (positioned) cover it overlaps. --}}
        <div style="position:relative;z-index:1;display:flex;gap:1rem;align-items:flex-end;padding:0 1.25rem;margin-top:-40px">
            <img id="avatar-preview" src="{{ $user->avatarUrl() }}" alt="Avatar"
                 class="avatar-ring" style="width:100px;height:100px">
            <div style="padding-bottom:.4rem">
                <label class="btn btn-ghost btn-sm" for="avatar" style="cursor:pointer">
                    <x-icon name="upload" :size="15" /> Change photo
                </label>
                <input type="file" id="avatar" name="avatar" accept="image/*" hidden data-image-preview="#avatar-preview">
            </div>
        </div>

        <div class="card-pad" style="padding-top:1.25rem">
            @error('avatar') <p class="error-text" style="margin-bottom:.8rem">{{ $message }}</p> @enderror
            @error('cover') <p class="error-text" style="margin-bottom:.8rem">{{ $message }}</p> @enderror

            <div class="form-row">
                <div class="field">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                           class="{{ $errors->has('name') ? 'input-error' : '' }}" required>
                    @error('name') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="{{ old('username', $user->username) }}"
                           class="{{ $errors->has('username') ? 'input-error' : '' }}" required>
                    @error('username') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="designation">Designation or job role</label>
                    <input type="text" id="designation" name="designation"
                           value="{{ old('designation', $user->designation) }}"
                           placeholder="Medical Officer, Grade I">
                    @error('designation') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="email">Email <span class="muted" style="font-weight:400">(optional)</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                           class="{{ $errors->has('email') ? 'input-error' : '' }}">
                    @error('email') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="field">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio" rows="3" maxlength="1000"
                          placeholder="A line or two about what you do here.">{{ old('bio', $user->bio) }}</textarea>
                @error('bio') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="qualifications">Qualifications</label>
                <textarea id="qualifications" name="qualifications" rows="2"
                          placeholder="MBBS (Colombo), MSc Health Informatics">{{ old('qualifications', $user->qualifications) }}</textarea>
                @error('qualifications') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="skills">Skills</label>
                <input type="text" id="skills" name="skills" value="{{ old('skills', $user->skills) }}"
                       placeholder="Data analysis, Laravel, Training">
                <p class="hint">Comma separated. Colleagues can search the directory by these.</p>
                @error('skills') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <button class="btn" type="submit">Save changes</button>
        </div>
    </form>
@endsection

@section('aside')
    <section class="panel">
        <div class="panel-head">
            <x-icon name="eye" :size="18" />
            <h3>How you appear</h3>
        </div>

        <div class="member-card" style="box-shadow:none;border-color:var(--line-soft)">
            <x-avatar :user="$user" :size="72" :presence="true" />
            <div class="mc-name">{{ $user->name }}</div>
            <div class="mc-role">{{ $user->designation ?: 'Add your job role' }}</div>
            <div class="mc-status">
                <span class="online-pill">Online now</span>
            </div>
            @if ($user->skillList())
                <div class="chip-row">
                    @foreach (array_slice($user->skillList(), 0, 3) as $skill)
                        <span class="chip">{{ $skill }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <x-icon name="lock" :size="18" />
            <h3>Change password</h3>
        </div>

        <form method="POST" action="{{ route('profile.password') }}">
            @csrf

            <div class="field">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password"
                       class="{{ $errors->has('current_password') ? 'input-error' : '' }}"
                       autocomplete="current-password">
                @error('current_password') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="password"
                       class="{{ $errors->has('password') ? 'input-error' : '' }}"
                       autocomplete="new-password">
                @error('password') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       autocomplete="new-password">
            </div>

            <button class="btn btn-ghost btn-block" type="submit">Change password</button>
        </form>
    </section>
@endsection
