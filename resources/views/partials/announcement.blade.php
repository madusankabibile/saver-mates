{{-- Site-wide banner set from Admin > Settings. Dismissal is remembered per
     message, so a new announcement shows again. --}}
@php
    $announcement = trim((string) \App\Models\Setting::get('announcement'));
@endphp

@if ($announcement !== '')
    <div class="announcement is-{{ \App\Models\Setting::get('announcement_tone', 'info') }}"
         data-announcement="{{ substr(md5($announcement), 0, 10) }}" role="status">
        <x-icon name="bell" :size="17" />
        <span>{{ $announcement }}</span>
        <button type="button" class="announcement-close" aria-label="Dismiss announcement">&#10005;</button>
    </div>
@endif
