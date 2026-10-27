{{-- Group "photo": the group's initials on a colour picked from its id. --}}
<span class="group-avatar" style="--hue: {{ $group->groupHue() }}; width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size * .36) }}px"
      aria-hidden="true">{{ $group->groupInitials() }}</span>
