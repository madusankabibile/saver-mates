@props(['user', 'size' => 42, 'presence' => false])

{{-- The a-{size} classes cover the sizes the design uses; the inline width and
     height make any other size render correctly instead of falling back to the
     image's natural dimensions. --}}
<span {{ $attributes->merge([
        'class' => 'avatar a-'.$size.($user->isOnline() ? ' is-online' : ''),
        'style' => 'width:'.$size.'px;height:'.$size.'px',
    ]) }}>
    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" loading="lazy">
    @if ($presence)
        <i class="presence" title="{{ $user->lastSeenLabel() }}"></i>
    @endif
</span>
