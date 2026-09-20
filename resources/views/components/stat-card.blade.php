@props([
    'label' => '',
    'value' => '',
    'hint' => null,
    'tone' => 'default',
])

@php
    $toneClass = match ($tone) {
        'brand' => 'text-ink',
        'danger' => 'text-danger',
        'success' => 'text-success',
        default => 'text-ink',
    };
@endphp

<div {{ $attributes->merge(['class' => 'bg-paper rounded-md border border-line p-5']) }}>
    <div class="text-[13px] font-medium text-ink-soft">{{ $label }}</div>
    <div class="mt-2 font-num text-3xl font-bold tabular {{ $toneClass }}">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-ink-soft">{{ $hint }}</div>
    @endif
</div>