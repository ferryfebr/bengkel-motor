<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">{{ __('Tambah Mekanik') }}</h1>
    </x-slot>

    <div class="max-w-xl mx-auto">
        @include('manage.partials.nav')
        <div class="bg-white border border-line rounded-md p-6">
            <form method="POST" action="{{ route('manage.mechanics.store') }}" class="space-y-6">
                @csrf
                <div>
                    <x-input-label for="name" :value="__('Nama Mekanik')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="mechanic_percentage" :value="__('Rasio Porsi Mekanik (%)')" />
                    <x-text-input id="mechanic_percentage" name="mechanic_percentage" type="number" step="0.01" min="0" max="100"
                                  class="mt-1 block w-full" :value="old('mechanic_percentage', 80)" required />
                    <x-input-error :messages="$errors->get('mechanic_percentage')" class="mt-2" />
                    <p class="text-xs text-ink-400 mt-1">Contoh: senior 85, junior 70.</p>
                </div>
                <div>
                    <x-input-label for="bengkel_percentage" :value="__('Rasio Porsi Bengkel (%)')" />
                    <x-text-input id="bengkel_percentage" name="bengkel_percentage" type="number" step="0.01" min="0" max="100"
                                  class="mt-1 block w-full" :value="old('bengkel_percentage', 20)" required />
                    <x-input-error :messages="$errors->get('bengkel_percentage')" class="mt-2" />
                    <p class="text-xs text-ink-400 mt-1">Rasio mekanik + bengkel harus berjumlah 100%.</p>
                </div>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-line text-signal focus:ring-signal" @checked(old('is_active', true))>
                    <span class="text-sm text-ink-600">Aktif</span>
                </label>
                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Simpan') }}</x-primary-button>
                    <a href="{{ route('manage.mechanics.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var m = document.getElementById('mechanic_percentage');
            var b = document.getElementById('bengkel_percentage');
            if (!m || !b) return;
            m.addEventListener('input', function () {
                var v = parseFloat(m.value);
                if (!isNaN(v)) b.value = String(Math.round((100 - v) * 100) / 100);
            });
        })();
    </script>
</x-app-layout>