<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Edit Produk') }}</h1>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        @include('manage.partials.nav')
        <div class="bg-white border border-line rounded-md p-6">
            <form method="POST" action="{{ route('manage.products.update', $product) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('manage.products.partials.form', ['product' => $product])

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Perbarui') }}</x-primary-button>
                    <a href="{{ route('manage.products.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink hover:underline">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>