@if (session('status'))
    <div class="flash">
        <x-icon name="check" :size="18" />
        <span>{{ session('status') }}</span>
        <button class="flash-close" type="button" aria-label="Dismiss">&#10005;</button>
    </div>
@endif

{{-- Sign-in and registration errors are shown inside the auth popup instead. --}}
@if ($errors->any() && ! old('_auth'))
    <div class="flash is-error">
        <x-icon name="close" :size="18" />
        <span>{{ $errors->first() }}</span>
        <button class="flash-close" type="button" aria-label="Dismiss">&#10005;</button>
    </div>
@endif
