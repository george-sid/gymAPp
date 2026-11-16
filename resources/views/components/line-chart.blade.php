{{--
    Single-series line chart over time.
    points: list of ['timestamp' => int, 'label' => 'd/m/Y', 'value' => float], ascending by time.
--}}
@props(['points', 'unit' => '', 'title' => ''])

@php
    $width = 640;
    $height = 220;
    $padLeft = 48;
    $padRight = 56;
    $padTop = 16;
    $padBottom = 30;
    $plotWidth = $width - $padLeft - $padRight;
    $plotHeight = $height - $padTop - $padBottom;

    // Round axis ticks (1, 2, 2.5 or 5 × 10^n apart) that enclose the data with some headroom.
    $values = array_column($points, 'value');
    $range = max(max($values) - min($values), max(abs(max($values)) * 0.02, 1));
    $rawStep = $range * 1.3 / 4;
    $magnitude = 10 ** floor(log10($rawStep));
    $residual = $rawStep / $magnitude;
    $step = match (true) {
        $residual <= 1 => 1,
        $residual <= 2 => 2,
        $residual <= 2.5 => 2.5,
        $residual <= 5 => 5,
        default => 10,
    } * $magnitude;
    $min = floor((min($values) - $range * 0.15) / $step) * $step;
    $max = ceil((max($values) + $range * 0.15) / $step) * $step;

    $times = array_column($points, 'timestamp');
    $tMin = min($times);
    $tSpan = max(max($times) - $tMin, 1);

    $format = fn (float $value): string => rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');

    $plotted = array_map(fn (array $point) => [
        ...$point,
        'x' => count($points) === 1 ? $padLeft + $plotWidth / 2 : $padLeft + ($point['timestamp'] - $tMin) / $tSpan * $plotWidth,
        'y' => $padTop + (1 - ($point['value'] - $min) / ($max - $min)) * $plotHeight,
    ], $points);

    $gridValues = array_map(fn (int $index) => $min + $index * $step, range(0, (int) round(($max - $min) / $step)));
    $last = end($plotted);
    $first = reset($plotted);
@endphp

<figure
    x-data="{ active: null }"
    class="relative"
    role="img"
    aria-label="{{ $title }}: από {{ $format($first['value']) }} {{ $unit }} ({{ $first['label'] }}) σε {{ $format($last['value']) }} {{ $unit }} ({{ $last['label'] }})"
>
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-auto w-full overflow-visible" aria-hidden="true">
        {{-- Recessive grid and y-axis labels --}}
        @foreach ($gridValues as $gridValue)
            @php($gridY = $padTop + (1 - ($gridValue - $min) / ($max - $min)) * $plotHeight)
            <line x1="{{ $padLeft }}" x2="{{ $width - $padRight }}" y1="{{ $gridY }}" y2="{{ $gridY }}" class="stroke-zinc-200" stroke-width="1" />
            <text x="{{ $padLeft - 8 }}" y="{{ $gridY + 4 }}" text-anchor="end" class="fill-zinc-500 text-[11px] tabular-nums">{{ $format($gridValue) }}</text>
        @endforeach

        {{-- X-axis: first and last date --}}
        <text x="{{ $first['x'] }}" y="{{ $height - 8 }}" text-anchor="{{ count($plotted) === 1 ? 'middle' : 'start' }}" class="fill-zinc-500 text-[11px]">{{ $first['label'] }}</text>
        @if (count($plotted) > 1)
            <text x="{{ $last['x'] }}" y="{{ $height - 8 }}" text-anchor="end" class="fill-zinc-500 text-[11px]">{{ $last['label'] }}</text>
        @endif

        {{-- Crosshair --}}
        @foreach ($plotted as $index => $point)
            <line x-show="active === {{ $index }}" x-cloak x1="{{ $point['x'] }}" x2="{{ $point['x'] }}" y1="{{ $padTop }}" y2="{{ $height - $padBottom }}" class="stroke-zinc-400" stroke-width="1" stroke-dasharray="3 3" />
        @endforeach

        {{-- Series: 2px line, 8px markers with a 2px surface ring --}}
        @if (count($plotted) > 1)
            <polyline
                points="{{ collect($plotted)->map(fn ($point) => round($point['x'], 1).','.round($point['y'], 1))->implode(' ') }}"
                fill="none"
                class="stroke-emerald-600"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
        @endif

        @foreach ($plotted as $index => $point)
            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" class="fill-emerald-600 stroke-white" stroke-width="2" />
        @endforeach

        {{-- Direct label on the latest value --}}
        <text x="{{ $last['x'] + 10 }}" y="{{ $last['y'] + 4 }}" class="fill-zinc-900 text-[12px] font-semibold tabular-nums">{{ $format($last['value']) }} {{ $unit }}</text>

        {{-- Hit targets, larger than the marks --}}
        @foreach ($plotted as $index => $point)
            <circle
                cx="{{ $point['x'] }}"
                cy="{{ $point['y'] }}"
                r="14"
                fill="transparent"
                x-on:mouseenter="active = {{ $index }}"
                x-on:mouseleave="active = null"
                x-on:click="active = active === {{ $index }} ? null : {{ $index }}"
            />
        @endforeach
    </svg>

    {{-- Tooltip --}}
    @foreach ($plotted as $index => $point)
        <div
            x-show="active === {{ $index }}"
            x-cloak
            class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg border border-zinc-200 bg-white px-2.5 py-1.5 text-xs whitespace-nowrap shadow-md"
            style="left: {{ round($point['x'] / $width * 100, 2) }}%; top: calc({{ round($point['y'] / $height * 100, 2) }}% - 10px)"
        >
            <div class="text-zinc-500">{{ $point['label'] }}</div>
            <div class="font-semibold text-zinc-900 tabular-nums">{{ $format($point['value']) }} {{ $unit }}</div>
        </div>
    @endforeach
</figure>
