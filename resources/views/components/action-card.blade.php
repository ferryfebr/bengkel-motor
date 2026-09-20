@props(['href' => '#', 'title' => '', 'desc' => null, 'icon' => null])

<a href="{{ $href }}"
   class="group flex items-start gap-4 bg-paper rounded-md border border-line p-5 hover:border-ink transition-colors">
    @if ($icon)
        <span class="shrink-0 flex items-center justify-center w-11 h-11 rounded-md bg-ink text-signal group-hover:bg-signal group-hover:text-ink transition-colors">
            {!! $icon !!}
        </span>
    @endif
    <span class="min-w-0">
        <span class="block font-semibold text-ink">{{ $title }}</span>
        @if ($desc)
            <span class="block text-sm text-ink-soft mt-0.5">{{ $desc }}</span>
        @endif
    </span>
</a>