<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">
            {{ __('Profile') }}
        </h1>
    </x-slot>

    <div class="max-w-3xl mx-auto space-y-6">
        <div class="p-6 bg-white border border-line rounded-md">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="p-6 bg-white border border-line rounded-md">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-app-layout>