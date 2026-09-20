@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-[13px] text-ink-500']) }}>
    {{ $value ?? $slot }}
</label>