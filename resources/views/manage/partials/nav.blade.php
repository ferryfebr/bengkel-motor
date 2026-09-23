@php
    $links = [
        ['route' => 'manage.products.index', 'label' => 'Produk', 'roles' => ['owner', 'super_admin', 'kasir']],
        ['route' => 'manage.categories.index', 'label' => 'Kategori', 'roles' => ['owner', 'super_admin']],
        ['route' => 'manage.mechanics.index', 'label' => 'Mekanik', 'roles' => ['owner', 'super_admin']],
        ['route' => 'manage.users.index', 'label' => 'Akun Kasir', 'roles' => ['owner', 'super_admin']],
    ];
@endphp

<div class="mb-6 flex flex-wrap gap-2 border-b border-line">
    @foreach ($links as $link)
        @if (auth()->user()->hasRole(...$link['roles']))
            @php $active = request()->routeIs($link['route'].'*'); @endphp
            <a href="{{ route($link['route']) }}"
               class="px-4 py-2.5 -mb-px text-sm font-medium border-b-2 transition
                      {{ $active ? 'border-signal text-ink' : 'border-transparent text-ink-500 hover:text-ink hover:border-line' }}">
                {{ $link['label'] }}
            </a>
        @endif
    @endforeach
</div>