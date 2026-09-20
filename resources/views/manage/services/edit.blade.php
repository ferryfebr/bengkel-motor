<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Edit Jasa') }}</h1>
    </x-slot>

    <div class="max-w-xl mx-auto">
        @include('manage.partials.nav')
        <div class="bg-white border border-line rounded-md p-6">
            <form method="POST" action="{{ route('manage.services.update', $service) }}" class="space-y-6">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label for="name" :value="__('Nama Jasa')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $service->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="price" :value="__('Tarif Template')" />
                    <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price', $service->price)" required />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-line text-signal focus:ring-signal" @checked(old('is_active', $service->is_active))>
                    <span class="text-sm text-ink-600">Aktif</span>
                </label>
                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Perbarui') }}</x-primary-button>
                    <a href="{{ route('manage.services.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink hover:underline">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>