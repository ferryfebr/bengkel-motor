@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-line focus:border-signal focus:ring-signal rounded-md min-h-[44px]']) }}>