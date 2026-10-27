{{-- Inline SVG column chart. $columns: [['label' => 'Mon', 'tip' => 'Head|line|line',
     'segments' => [['class' => 's-posts', 'value' => 3], ...]], ...]. Stacked when a
     column has several segments. $axisEvery picks which x labels to print. --}}
@php
    $W = 640; $H = 190; $padL = 28; $padB = 22; $padT = 8;
    $plotW = $W - $padL; $plotH = $H - $padB - $padT;
    $max = max(1, collect($columns)->map(fn ($c) => array_sum(array_column($c['segments'], 'value')))->max());
    // A round axis top: 1, 2, 5 x 10^n.
    $mag = 10 ** floor(log10($max)); $top = collect([1, 2, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($v) => $v >= $max);
    $n = count($columns); $slot = $plotW / max(1, $n); $barW = max(3, min(26, $slot * .62));
    $axisEvery = $axisEvery ?? 1;
@endphp

<svg class="chart" viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ $aria ?? 'Chart' }}">
    <g class="grid">
        @foreach ([0, .5, 1] as $f)
            @php $y = $padT + $plotH * (1 - $f); @endphp
            <line x1="{{ $padL }}" x2="{{ $W }}" y1="{{ $y }}" y2="{{ $y }}" />
        @endforeach
    </g>
    <g class="axis">
        @foreach ([0, .5, 1] as $f)
            <text x="{{ $padL - 6 }}" y="{{ $padT + $plotH * (1 - $f) + 3 }}" text-anchor="end">{{ number_format($top * $f) == $top * $f ? number_format($top * $f) : number_format($top * $f, 1) }}</text>
        @endforeach
        @foreach ($columns as $i => $col)
            @if ($i % $axisEvery === 0 || $i === $n - 1)
                <text x="{{ $padL + $slot * $i + $slot / 2 }}" y="{{ $H - 6 }}" text-anchor="middle">{{ $col['label'] }}</text>
            @endif
        @endforeach
    </g>

    @foreach ($columns as $i => $col)
        @php $x = $padL + $slot * $i + ($slot - $barW) / 2; $base = $padT + $plotH; @endphp
        <g class="col" data-tip="{{ $col['tip'] }}">
            <rect class="hit" x="{{ $padL + $slot * $i }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" />
            @foreach ($col['segments'] as $seg)
                @continue($seg['value'] <= 0)
                @php $h = max(2, $plotH * $seg['value'] / $top); $base -= $h; @endphp
                <rect class="bar {{ $seg['class'] }}" x="{{ $x }}" y="{{ $base }}" width="{{ $barW }}" height="{{ max(1, $h - 2) }}" rx="2" />
            @endforeach
        </g>
    @endforeach
</svg>
