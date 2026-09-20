<x-guest-layout>
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-ink">Konfirmasi Password</h1>
        <p class="text-sm text-ink-500 mt-1">{{ __('Ini area aman. Masukkan password Anda sebelum melanjutkan.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-6">
            <x-primary-button class="w-full justify-center">
                {{ __('Konfirmasi') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>