<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Tambah Kategori') }}</h1>
    </x-slot>

    <div class="max-w-xl mx-auto">
        @include('manage.partials.nav')
        <div class="bg-white border border-line rounded-md p-6">
            <form method="POST" action="{{ route('manage.categories.store') }}" class="space-y-6">
                @csrf
                <div>
                    <x-input-label for="name" :value="__('Nama Kategori')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Simpan') }}</x-primary-button>
                    <a href="{{ route('manage.categories.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink hover:underline">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>