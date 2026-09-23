<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-bold text-ink">Motor Masuk (Work Order Baru)</h1>
    </x-slot>

    <div class="max-w-xl mx-auto">
        <div class="bg-white border border-line rounded-md p-6">
            <p class="text-sm text-ink-500 mb-5">
                Kasir boleh membuat banyak Work Order sekaligus. Tidak perlu menuntaskan WO lain lebih dulu.
            </p>

            @if ($errors->any())
                <div class="mb-4 bg-danger-light border border-danger/40 text-danger px-4 py-3 rounded-md text-sm">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('work-orders.store') }}" class="space-y-5">
                @csrf
                <div>
                    <x-input-label for="plate_number" :value="__('Plat Nomor')" />
                    <x-text-input id="plate_number" name="plate_number" type="text" class="mt-1 block w-full uppercase"
                                  :value="old('plate_number')" required autofocus placeholder="B 1234 XY" />
                    <x-input-error :messages="$errors->get('plate_number')" class="mt-2" />
                    <p class="text-xs text-ink-400 mt-1">Wajib diisi. Isi <strong>XXXX</strong> bila motor tidak berplat nomor.</p>
                </div>
                <div>
                    <x-input-label for="customer_name" :value="__('Nama Customer')" />
                    <x-text-input id="customer_name" name="customer_name" type="text" class="mt-1 block w-full"
                                  :value="old('customer_name')" />
                    <x-input-error :messages="$errors->get('customer_name')" class="mt-2" />
                    <p class="text-xs text-ink-400 mt-1" id="customer-hint">Wajib diisi bila plat nomor XXXX.</p>
                </div>
                <div>
                    <x-input-label for="motor_type" :value="__('Jenis Motor')" />
                    <x-text-input id="motor_type" name="motor_type" type="text" class="mt-1 block w-full"
                                  :value="old('motor_type')" placeholder="mis. Honda Vario 125, Yamaha NMAX" />
                    <x-input-error :messages="$errors->get('motor_type')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="complaint" :value="__('Keluhan')" />
                    <textarea id="complaint" name="complaint" rows="3" class="mt-1 block w-full border-line rounded-md focus:border-signal focus:ring-signal">{{ old('complaint') }}</textarea>
                    <x-input-error :messages="$errors->get('complaint')" class="mt-2" />
                </div>
                <div class="flex items-center gap-4 pt-1">
                    <x-primary-button>{{ __('Simpan Motor Masuk') }}</x-primary-button>
                    <a href="{{ route('work-orders.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var plate = document.getElementById('plate_number');
            var customer = document.getElementById('customer_name');
            if (!plate || !customer) return;
            function sync() {
                if (plate.value.trim().toUpperCase() === 'XXXX') {
                    customer.setAttribute('required', 'required');
                } else {
                    customer.removeAttribute('required');
                }
            }
            plate.addEventListener('input', sync);
            sync();
        })();
    </script>
</x-app-layout>