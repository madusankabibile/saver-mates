{{-- Sign-in and create-account popup, present on every page for guests.
     Any element with data-auth-open="login|register" opens it. It reopens on
     load after a no-JS form error (old('_auth')) or a redirect from /login. --}}
@php
    $registrationOpen = \App\Models\Setting::flag('registration_open');
    $failed = old('_auth');
    $start = $failed ?: session('auth_modal');
    $loginErrors = $failed === 'login' ? $errors : new \Illuminate\Support\ViewErrorBag;
    $registerErrors = $failed === 'register' ? $errors : new \Illuminate\Support\ViewErrorBag;
@endphp

<div class="auth-modal" data-auth-modal data-start="{{ $start }}" hidden>
    <div class="am-dialog" role="dialog" aria-modal="true" aria-labelledby="am-title">
        <aside class="am-aside">
            <span class="brand">
                <span class="brand-mark" aria-hidden="true"><x-icon name="sparkle" :size="19" /></span>
                <span>SaverMate<span class="brand-sub">Local network</span></span>
            </span>

            <h2 data-am-aside-title>Welcome back to the network</h2>

            <ul class="auth-points">
                <li>
                    <span class="ap-icon"><x-icon name="feed" :size="15" /></span>
                    <span>A shared newsfeed your whole team can react to.</span>
                </li>
                <li>
                    <span class="ap-icon"><x-icon name="chat" :size="15" /></span>
                    <span>Private chat with anyone on the network.</span>
                </li>
                <li>
                    <span class="ap-icon"><x-icon name="shield" :size="15" /></span>
                    <span>A cloud drive that stays private until you publish it.</span>
                </li>
            </ul>
        </aside>

        <div class="am-main">
            <button class="am-close" type="button" data-auth-close aria-label="Close">
                <x-icon name="close" :size="18" />
            </button>

            <h2 class="sr-only" id="am-title">Sign in or create an account</h2>

            @if ($registrationOpen)
                <div class="am-tabs" role="tablist">
                    <button type="button" role="tab" data-auth-tab="login">Sign in</button>
                    <button type="button" role="tab" data-auth-tab="register">Create account</button>
                </div>
            @endif

            {{-- Sign in -------------------------------------------------- --}}
            <form class="am-form" data-auth-form="login" method="POST" action="{{ route('login') }}" novalidate>
                @csrf
                <input type="hidden" name="_auth" value="login">
                <input type="hidden" name="return_to" value="{{ url()->current() }}">

                <p class="sub">Use the username you registered with.</p>

                <div class="field">
                    <label for="login-username">Username</label>
                    <input type="text" id="login-username" name="username"
                           value="{{ $failed === 'login' ? old('username') : '' }}"
                           class="{{ $loginErrors->has('username') ? 'input-error' : '' }}"
                           required autocomplete="username">
                    <p class="error-text" data-error-for="username" @unless ($loginErrors->has('username')) hidden @endunless>{{ $loginErrors->first('username') }}</p>
                </div>

                <div class="field">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password"
                           class="{{ $loginErrors->has('password') ? 'input-error' : '' }}"
                           required autocomplete="current-password">
                    <p class="error-text" data-error-for="password" @unless ($loginErrors->has('password')) hidden @endunless>{{ $loginErrors->first('password') }}</p>
                </div>

                <label class="checkline" style="margin-bottom:1.2rem">
                    <input type="checkbox" name="remember" value="1">
                    Keep me signed in on this computer
                </label>

                <button class="btn btn-lg btn-block" type="submit" data-auth-submit>Sign in</button>

                <p class="auth-foot">
                    @if ($registrationOpen)
                        No account yet? <a href="{{ route('register') }}" data-auth-tab="register">Create one</a>
                    @else
                        New accounts are created by a network administrator.
                    @endif
                </p>
            </form>

            {{-- Create account ------------------------------------------ --}}
            @if ($registrationOpen)
                <form class="am-form" data-auth-form="register" method="POST" action="{{ route('register') }}"
                      enctype="multipart/form-data" novalidate hidden>
                    @csrf
                    <input type="hidden" name="_auth" value="register">

                    <p class="sub">Takes a minute. You can add a bio and cover banner afterwards.</p>

                    @php $old = fn ($k) => $failed === 'register' ? old($k) : ''; @endphp

                    <div class="field">
                        <label for="reg-name">Full name</label>
                        <input type="text" id="reg-name" name="name" value="{{ $old('name') }}"
                               class="{{ $registerErrors->has('name') ? 'input-error' : '' }}"
                               placeholder="Nimal Perera" required>
                        <p class="error-text" data-error-for="name" @unless ($registerErrors->has('name')) hidden @endunless>{{ $registerErrors->first('name') }}</p>
                    </div>

                    <div class="field">
                        <label for="reg-username">Username</label>
                        <input type="text" id="reg-username" name="username" value="{{ $old('username') }}"
                               class="{{ $registerErrors->has('username') ? 'input-error' : '' }}"
                               placeholder="nimal.perera" required autocomplete="username">
                        <p class="hint">Letters, numbers, dots, dashes and underscores.</p>
                        <p class="error-text" data-error-for="username" @unless ($registerErrors->has('username')) hidden @endunless>{{ $registerErrors->first('username') }}</p>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="reg-password">Password</label>
                            <input type="password" id="reg-password" name="password"
                                   class="{{ $registerErrors->has('password') ? 'input-error' : '' }}"
                                   required autocomplete="new-password" placeholder="8+ characters">
                            <p class="error-text" data-error-for="password" @unless ($registerErrors->has('password')) hidden @endunless>{{ $registerErrors->first('password') }}</p>
                        </div>

                        <div class="field">
                            <label for="reg-password2">Confirm password</label>
                            <input type="password" id="reg-password2" name="password_confirmation"
                                   required autocomplete="new-password">
                        </div>
                    </div>

                    <div class="field">
                        <label for="reg-designation">Designation or job role <span class="muted">(optional)</span></label>
                        <input type="text" id="reg-designation" name="designation" value="{{ $old('designation') }}"
                               placeholder="Medical Officer, Grade I">
                        <p class="error-text" data-error-for="designation" @unless ($registerErrors->has('designation')) hidden @endunless>{{ $registerErrors->first('designation') }}</p>
                    </div>

                    <details class="am-more" @if ($registerErrors->hasAny(['qualifications', 'skills', 'avatar'])) open @endif>
                        <summary>Add qualifications, skills and a photo</summary>

                        <div class="field">
                            <label for="reg-qualifications">Qualifications</label>
                            <textarea id="reg-qualifications" name="qualifications" rows="2"
                                      placeholder="MBBS (Colombo), MSc Health Informatics">{{ $old('qualifications') }}</textarea>
                            <p class="error-text" data-error-for="qualifications" @unless ($registerErrors->has('qualifications')) hidden @endunless>{{ $registerErrors->first('qualifications') }}</p>
                        </div>

                        <div class="field">
                            <label for="reg-skills">Skills</label>
                            <input type="text" id="reg-skills" name="skills" value="{{ $old('skills') }}"
                                   placeholder="Data analysis, Laravel, Training">
                            <p class="hint">Separate each skill with a comma.</p>
                            <p class="error-text" data-error-for="skills" @unless ($registerErrors->has('skills')) hidden @endunless>{{ $registerErrors->first('skills') }}</p>
                        </div>

                        <div class="field">
                            <div class="avatar-picker">
                                <img id="reg-avatar-preview" src="{{ asset('img/avatar-placeholder.svg') }}" alt="">
                                <div style="flex:1;min-width:0">
                                    <label class="btn btn-ghost btn-sm" for="reg-avatar" style="cursor:pointer">
                                        <x-icon name="upload" :size="15" /> Choose a photo
                                    </label>
                                    <p class="hint" style="margin-top:.4rem">JPG, PNG or WebP, up to 4&nbsp;MB.</p>
                                </div>
                            </div>
                            <input type="file" id="reg-avatar" name="avatar" accept="image/*" hidden
                                   data-image-preview="#reg-avatar-preview">
                            <p class="error-text" data-error-for="avatar" @unless ($registerErrors->has('avatar')) hidden @endunless>{{ $registerErrors->first('avatar') }}</p>
                        </div>
                    </details>

                    <button class="btn btn-lg btn-block" type="submit" data-auth-submit>Create account</button>

                    <p class="auth-foot">
                        Already a member? <a href="{{ route('login') }}" data-auth-tab="login">Sign in</a>
                    </p>
                </form>
            @endif
        </div>
    </div>
</div>
