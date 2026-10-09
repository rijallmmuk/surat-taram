<div class="-mt-2 mb-1" x-data="{
    inserted: false,
    text: '',
    insertTag: function (tag) {
        var input = document.getElementById('input_pola_format_nomor')
                 || document.querySelector('input[id*=pola_format_nomor]')
                 || document.querySelector('input[wire\\:model*=pola_format_nomor]');
        if (input) {
            var start = input.selectionStart != null ? input.selectionStart : input.value.length;
            var end = input.selectionEnd != null ? input.selectionEnd : input.value.length;
            var val = input.value;
            input.value = val.substring(0, start) + tag + val.substring(end);
            var newPos = start + tag.length;
            input.focus();
            input.setSelectionRange(newPos, newPos);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            input.dispatchEvent(new Event('blur', { bubbles: true }));
        }
        this.text = tag;
        this.inserted = true;
        var self = this;
        setTimeout(function () {
            self.inserted = false;
        }, 2000);
    }
}">
    <div class="mb-1.5 flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
            Klik tombol komponen di bawah untuk menyusun pola nomor:
        </span>
        <span x-show="inserted" x-transition class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
            ✓ Disisipkan: <span x-text="text" class="font-bold"></span>
        </span>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @php
            $components = [
                ['tag' => '[Kode Klasifikasi]', 'label' => 'Kode Klasifikasi'],
                ['tag' => '[Nomor Urut]', 'label' => 'Nomor Urut'],
                ['tag' => '[Kode Unit]', 'label' => 'Kode Unit'],
                ['tag' => '[Tahun]', 'label' => 'Tahun'],
            ];
        @endphp

        @foreach ($components as $item)
            <button
                type="button"
                @click="insertTag('{{ $item['tag'] }}')"
                class="group inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-primary-300 bg-primary-50/50 px-3 text-xs font-semibold text-primary-800 shadow-2xs transition hover:border-primary-500 hover:bg-primary-100 hover:text-primary-900 dark:border-primary-800 dark:bg-primary-950/40 dark:text-primary-300 dark:hover:bg-primary-900 cursor-pointer"
                title="Klik untuk menyisipkan {{ $item['label'] }}"
            >
                <span class="text-primary-600 dark:text-primary-400 font-bold">+</span>
                <span>{{ $item['label'] }}</span>
            </button>
        @endforeach

        <span class="text-xs text-gray-400 mx-1">Tanda Pemisah:</span>

        <button
            type="button"
            @click="insertTag('/')"
            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-2.5 text-xs font-bold text-gray-700 shadow-2xs transition hover:border-primary-400 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 cursor-pointer"
            title="Sisipkan tanda garis miring (/)"
        >
            /
        </button>

        <button
            type="button"
            @click="insertTag('-')"
            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-2.5 text-xs font-bold text-gray-700 shadow-2xs transition hover:border-primary-400 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 cursor-pointer"
            title="Sisipkan tanda hubung (-)"
        >
            -
        </button>
    </div>
</div>
