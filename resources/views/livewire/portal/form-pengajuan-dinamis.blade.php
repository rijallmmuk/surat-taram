<div class="max-w-3xl mx-auto">
    <!-- Breadcrumb & Judul -->
    <div class="mb-8">
        <a href="{{ url('/panel/pengajuan-wargas') }}" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1 mb-2">
            &larr; Kembali ke Pilihan Surat
        </a>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-tight">
            Pengajuan {{ $jenisSurat->nama_surat }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">Lengkapi data permohonan di bawah ini dengan benar.</p>
    </div>

    @if (session()->has('error'))
        <div class="p-4 mb-6 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="submit" class="space-y-8">
        <!-- SEKSI 1: DATA IDENTITAS PEMOHON (OTOMATIS DARI KEPENDUDUKAN) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="font-bold text-slate-900 text-base">1. Data Pemohon (Kependudukan)</h3>
                <span class="text-xs px-2.5 py-1 rounded bg-slate-100 text-slate-600 font-semibold">Otomatis Terisi</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-xs text-slate-400 block">Nama Lengkap</span>
                    <span class="font-semibold text-slate-800">{{ $penduduk->nama }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block">NIK</span>
                    <span class="font-semibold text-slate-800">{{ $penduduk->nik }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block">Tempat, Tanggal Lahir</span>
                    <span class="font-semibold text-slate-800">{{ $penduduk->tempat_lahir }}, {{ $penduduk->tanggal_lahir?->translatedFormat('d F Y') }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block">Jenis Kelamin</span>
                    <span class="font-semibold text-slate-800">{{ $penduduk->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block">Agama</span>
                    <span class="font-semibold text-slate-800">{{ $penduduk->agama?->nama ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block">Pekerjaan</span>
                    <span class="font-semibold text-slate-800">{{ $penduduk->pekerjaan?->nama ?? '-' }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-slate-400 block">Alamat / Jorong</span>
                    <span class="font-semibold text-slate-800">{{ $penduduk->alamat }}</span>
                </div>
            </div>
        </div>

        <!-- SEKSI 2: FORMULIR ISIAN KHUSUS SURAT DINAMIS -->
        @if ($jenisSurat->skemaFormFields->isNotEmpty())
            @php
                $ungroupedFields = $fieldBerlaku->filter(fn ($f) => empty($f->parent_group));
                $groupedFields = $fieldBerlaku->filter(fn ($f) => ! empty($f->parent_group))->groupBy('parent_group');
            @endphp

            @if ($ungroupedFields->isNotEmpty())
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                    <div class="border-b border-slate-100 pb-3">
                        <h3 class="font-bold text-slate-900 text-base">2. Formulir Keterangan Khusus</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Isi rincian informasi pokok yang dibutuhkan untuk penerbitan surat ini.</p>
                    </div>

                    @foreach ($ungroupedFields as $field)
                        @php
                            $fieldNameLower = strtolower($field->nama_field);
                            $isNik = preg_match('/(?:^|_)nik(?:_|$)/', $fieldNameLower);
                            $isDeathOrBirth = str_contains($fieldNameLower, 'meninggal') || str_contains($fieldNameLower, 'kematian') || str_contains($fieldNameLower, 'lahir');
                        @endphp
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-semibold text-slate-700">
                                    {{ $field->label }}
                                    @if ($field->wajib)
                                        <span class="text-red-500">*</span>
                                    @else
                                        <span class="text-xs text-slate-400 font-normal">(Opsional)</span>
                                    @endif
                                </label>
                                </div>

                            @if ($field->tipe_field === 'text')
                                <input 
                                    type="text" 
                                    wire:model.live="dataIsian.{{ $field->nama_field }}" 
                                    @if ($isNik) maxlength="16" inputmode="numeric" pattern="[0-9]{16}" @endif
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition"
                                >
                            @elseif ($field->tipe_field === 'textarea')
                                <textarea 
                                    wire:model="dataIsian.{{ $field->nama_field }}" 
                                    rows="3" 
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition"
                                ></textarea>
                            @elseif ($field->tipe_field === 'number')
                                <input
                                    type="number"
                                    wire:model="dataIsian.{{ $field->nama_field }}"
                                    min="0"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition"
                                >
                            @elseif ($field->tipe_field === 'date')
                                <input 
                                    type="date"
                                    lang="id-ID"
                                    wire:model="dataIsian.{{ $field->nama_field }}" 
                                    @if ($isDeathOrBirth) max="{{ date('Y-m-d') }}" @endif
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition"
                                >
                            @elseif ($field->tipe_field === 'select')
                                @php
                                    $opts = \App\Services\MasterReferensiHelper::getOptionsForField($field->referensi_master, $field->nama_field, $field->opsi_pilihan);
                                @endphp
                                <select 
                                    wire:model.live="dataIsian.{{ $field->nama_field }}" 
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition"
                                >
                                    <option value="">-- Pilih {{ $field->label }} --</option>
                                    @foreach ($opts as $optKey => $optVal)
                                        <option value="{{ $optKey }}">{{ $optVal }}</option>
                                    @endforeach
                                </select>
                            @elseif ($field->tipe_field === 'rich_text')
                                @include('livewire.portal.rich-text-input', ['field' => $field])
                            @elseif ($field->tipe_field === 'file')
                                <input
                                    type="file"
                                    aria-label="{{ $field->label }}"
                                    wire:key="file-{{ $field->id }}-{{ $fileResetKeys[$field->nama_field] ?? 0 }}"
                                    wire:model="dataIsian.{{ $field->nama_field }}"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:font-medium file:text-emerald-800 focus:ring-2 focus:ring-emerald-500"
                                >
                                <p class="text-xs text-slate-500">PDF, JPG, atau PNG. Maksimal 5 MB.</p>
                                @if (filled($dataIsian[$field->nama_field] ?? null))
                                    <button type="button" wire:click="hapusIsianFile('{{ $field->nama_field }}')" class="text-xs font-medium text-red-700 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">Hapus berkas pilihan</button>
                                @endif
                            @elseif ($field->tipe_field === 'table_repeater')
                                 <!-- Repeater Tabel Dinamis -->
                                <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/60 space-y-3">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">{{ $field->label }}</span>
                                        </div>
                                        <button 
                                            type="button" 
                                            wire:click="addRepeaterRow('{{ $field->nama_field }}')" 
                                            class="text-xs px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium transition"
                                        >
                                            + Tambah Baris
                                        </button>
                                    </div>

                                    @if (! empty($dataIsian[$field->nama_field]))
                                        <div class="space-y-3">
                                            @foreach ($dataIsian[$field->nama_field] as $idx => $row)
                                                <div class="p-3.5 bg-white border border-slate-200 rounded-xl flex items-start gap-3 shadow-xs">
                                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-xs font-bold flex items-center justify-center shrink-0 mt-6">
                                                        {{ $idx + 1 }}
                                                    </span>
                                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                                        @foreach ($field->kolomTabels as $kolom)
                                                            <div>
                                                                <label class="block text-xs font-medium text-slate-600 mb-1">{{ $kolom->label }}{{ ($kolom->wajib ?? true) ? '' : ' (opsional)' }}</label>
                                                                @if ($kolom->tipe_kolom === 'select')
                                                                    @php
                                                                        $subOpts = \App\Services\MasterReferensiHelper::getOptionsForField($kolom->referensi_master, $kolom->nama_kolom, $kolom->opsi_pilihan);
                                                                    @endphp
                                                                    <select 
                                                                        wire:model="dataIsian.{{ $field->nama_field }}.{{ $idx }}.{{ $kolom->nama_kolom }}"
                                                                        class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:ring-1 focus:ring-emerald-500"
                                                                    >
                                                                        <option value="">Pilih</option>
                                                                        @foreach ($subOpts as $sk => $sv)
                                                                            <option value="{{ $sk }}">{{ $sv }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                @else
                                                                    <input 
                                                                        type="{{ $kolom->tipe_kolom === 'number' ? 'number' : ($kolom->tipe_kolom === 'date' ? 'date' : 'text') }}" 
                                                                        wire:model="dataIsian.{{ $field->nama_field }}.{{ $idx }}.{{ $kolom->nama_kolom }}"
                                                                        @if ($kolom->tipe_kolom === 'text') maxlength="255" @endif
                                                                        class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:ring-1 focus:ring-emerald-500"
                                                                    >
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <button 
                                                        type="button" 
                                                        wire:click="removeRepeaterRow('{{ $field->nama_field }}', {{ $idx }})" 
                                                        class="text-red-400 hover:text-red-600 p-1 mt-6 transition"
                                                        title="Hapus baris"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @error("dataIsian.{$field->nama_field}")
                                <span class="text-xs text-red-600 font-medium block">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- GRUP BIDANG OPSIONAL (Contoh: Data Ayah / Data Ibu pada SKTM) -->
            @foreach ($groupedFields as $groupKey => $fieldsInGroup)
                @php
                    $isOptional = $fieldsInGroup->contains(fn ($field): bool => (bool) $field->is_optional_group);
                    $groupTitle = match ($groupKey) {
                        'data_ayah' => 'Sertakan Data Ayah Kandung',
                        'data_ibu' => 'Sertakan Data Ibu Kandung',
                        default => ucwords(str_replace('_', ' ', $groupKey)),
                    };
                    $groupSubtitle = match ($groupKey) {
                        'data_ayah', 'data_ibu' => 'Centang jika permohonan diajukan atas nama anak/siswa/mahasiswa (untuk beasiswa, KIP, keringanan biaya, dll).',
                        default => $isOptional ? 'Centang opsi ini jika ingin melampirkan keterangan kelompok data ini.' : 'Lengkapi keterangan berikut.',
                    };
                    $isActive = ! $isOptional || ($toggledGroups[$groupKey] ?? false);
                @endphp

                <div class="bg-white p-6 rounded-2xl border transition duration-200 shadow-sm space-y-4 {{ $isActive ? 'border-emerald-300 ring-1 ring-emerald-100' : 'border-slate-200' }}">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        @if ($isOptional)
                            <label class="flex items-start sm:items-center gap-3 cursor-pointer select-none">
                                <input 
                                    type="checkbox" 
                                    wire:click="toggleGroup('{{ $groupKey }}')"
                                    @checked($toggledGroups[$groupKey] ?? false)
                                    class="w-5 h-5 mt-0.5 sm:mt-0 text-emerald-600 rounded-md border-slate-300 focus:ring-emerald-500 cursor-pointer"
                                >
                                <div>
                                    <h3 class="font-bold text-slate-900 text-base leading-tight">{{ $groupTitle }}</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $groupSubtitle }}</p>
                                </div>
                            </label>
                            <span class="text-xs px-2.5 py-1 rounded-md font-semibold transition shrink-0 {{ ($toggledGroups[$groupKey] ?? false) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' }}">
                                {{ ($toggledGroups[$groupKey] ?? false) ? '✓ Disertakan' : 'Dilewati' }}
                            </span>
                        @else
                            <div>
                                <h3 class="font-bold text-slate-900 text-base">{{ $groupTitle }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $groupSubtitle }}</p>
                            </div>
                        @endif
                    </div>

                    @if ($isActive)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                            @foreach ($fieldsInGroup as $field)
                                @php
                                    $fieldNameLower = strtolower($field->nama_field);
                                    $isNik = preg_match('/(?:^|_)nik(?:_|$)/', $fieldNameLower);
                                    $isDeathOrBirth = str_contains($fieldNameLower, 'meninggal') || str_contains($fieldNameLower, 'kematian') || str_contains($fieldNameLower, 'lahir');
                                    $isColSpanFull = in_array($field->tipe_field, ['textarea', 'rich_text', 'table_repeater', 'file']) || str_contains($fieldNameLower, 'alamat');
                                @endphp
                                <div class="space-y-1.5 {{ $isColSpanFull ? 'sm:col-span-2' : '' }}">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-semibold text-slate-700">
                                            {{ $field->label }}
                                            @if ($field->wajib && $isActive)
                                                <span class="text-red-500">*</span>
                                            @endif
                                        </label>
                                        @if ($isNik)
                                            <span class="text-[11px] text-slate-400 font-normal">16 digit angka KTP</span>
                                        @endif
                                    </div>

                                    @if ($field->tipe_field === 'textarea')
                                        <textarea
                                            wire:model="dataIsian.{{ $field->nama_field }}"
                                            rows="3"
                                            class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition"
                                        ></textarea>
                                    @elseif ($field->tipe_field === 'rich_text')
                                        @include('livewire.portal.rich-text-input', ['field' => $field])
                                    @elseif ($field->tipe_field === 'file')
                                        <input
                                            type="file"
                                            aria-label="{{ $field->label }}"
                                            wire:key="file-{{ $field->id }}-{{ $fileResetKeys[$field->nama_field] ?? 0 }}"
                                            wire:model="dataIsian.{{ $field->nama_field }}"
                                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:font-medium file:text-emerald-800 focus:ring-2 focus:ring-emerald-500"
                                        >
                                        <p class="text-xs text-slate-500">PDF, JPG, atau PNG. Maksimal 5 MB.</p>
                                        @if (filled($dataIsian[$field->nama_field] ?? null))
                                            <button type="button" wire:click="hapusIsianFile('{{ $field->nama_field }}')" class="text-xs font-medium text-red-700 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">Hapus berkas pilihan</button>
                                        @endif
                                    @elseif ($field->tipe_field === 'date')
                                        <input 
                                            type="date"
                                            lang="id-ID"
                                            wire:model.live="dataIsian.{{ $field->nama_field }}" 
                                            @if ($isDeathOrBirth) max="{{ date('Y-m-d') }}" @endif
                                            class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition"
                                        >
                                    @elseif ($field->tipe_field === 'select')
                                        @php
                                            $opts = \App\Services\MasterReferensiHelper::getOptionsForField($field->referensi_master, $field->nama_field, $field->opsi_pilihan);
                                        @endphp
                                        <select 
                                            wire:model.live="dataIsian.{{ $field->nama_field }}" 
                                            class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition bg-white"
                                        >
                                            <option value="">-- Pilih {{ $field->label }} --</option>
                                            @foreach ($opts as $optVal => $optLabel)
                                                <option value="{{ $optVal }}">{{ $optLabel }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($field->tipe_field === 'number')
                                        <input 
                                            type="number" 
                                            min="0"
                                            wire:model="dataIsian.{{ $field->nama_field }}" 
                                            class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition"
                                        >
                                    @elseif ($field->tipe_field === 'table_repeater')
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-3">
                                            <button
                                                type="button"
                                                wire:click="addRepeaterRow('{{ $field->nama_field }}')"
                                                class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                            >
                                                + Tambah baris
                                            </button>
                                            @foreach (($dataIsian[$field->nama_field] ?? []) as $idx => $row)
                                                <div class="rounded-lg border border-slate-200 bg-white p-3 space-y-3" wire:key="group-row-{{ $field->id }}-{{ $idx }}">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span class="text-sm font-semibold text-slate-700">Baris {{ $idx + 1 }}</span>
                                                        <button
                                                            type="button"
                                                            wire:click="removeRepeaterRow('{{ $field->nama_field }}', {{ $idx }})"
                                                            class="rounded-lg px-2 py-1 text-sm text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                                                        >
                                                            Hapus baris
                                                        </button>
                                                    </div>
                                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                        @foreach ($field->kolomTabels as $kolom)
                                                            <div class="space-y-1">
                                                                <label class="block text-sm font-medium text-slate-700">{{ $kolom->label }}{{ ($kolom->wajib ?? true) ? '' : ' (opsional)' }}</label>
                                                                @if ($kolom->tipe_kolom === 'select')
                                                                    <select
                                                                        wire:model="dataIsian.{{ $field->nama_field }}.{{ $idx }}.{{ $kolom->nama_kolom }}"
                                                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500"
                                                                    >
                                                                        <option value="">Pilih {{ $kolom->label }}</option>
                                                                        @foreach (\App\Services\MasterReferensiHelper::getOptionsForField($kolom->referensi_master, $kolom->nama_kolom, $kolom->opsi_pilihan) as $optionKey => $optionLabel)
                                                                            <option value="{{ $optionKey }}">{{ $optionLabel }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                @else
                                                                    <input
                                                                        type="{{ $kolom->tipe_kolom === 'number' ? 'number' : ($kolom->tipe_kolom === 'date' ? 'date' : 'text') }}"
                                                                        wire:model="dataIsian.{{ $field->nama_field }}.{{ $idx }}.{{ $kolom->nama_kolom }}"
                                                                        @if ($kolom->tipe_kolom === 'text') maxlength="255" @endif
                                                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500"
                                                                    >
                                                                @endif
                                                                @error("dataIsian.{$field->nama_field}.{$idx}.{$kolom->nama_kolom}")
                                                                    <span class="block text-xs text-red-700">{{ $message }}</span>
                                                                @enderror
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <input 
                                            type="text" 
                                            wire:model="dataIsian.{{ $field->nama_field }}" 
                                            @if ($isNik) maxlength="16" inputmode="numeric" pattern="[0-9]{16}" @endif
                                            class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm transition"
                                        >
                                    @endif

                                    @error("dataIsian.{$field->nama_field}")
                                        <span class="text-xs text-red-600 font-medium block">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

        <!-- SEKSI 3: UPLOAD SYARAT DOKUMEN -->
        @if ($syaratBerlaku->isNotEmpty())
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-5">
                <div class="border-b border-slate-100 pb-3">
                    <h3 class="font-bold text-slate-900 text-base">3. Berkas Persyaratan Dokumen</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Lampirkan berkas persyaratan agar permohonan dapat segera diproses dan diverifikasi.</p>
                </div>

                <!-- Banner Informasi & Ketentuan Berkas -->
                <div class="p-4 rounded-xl bg-blue-50/90 border border-blue-200 text-blue-900 text-xs space-y-2">
                    <div class="flex items-center gap-2 font-bold text-blue-950 text-sm">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Petunjuk dan Ketentuan Unggah Berkas:
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-blue-800 leading-relaxed text-xs">
                        <li><strong>Format yang Didukung:</strong> Dokumen <strong>PDF</strong> atau Foto/Gambar (<strong>JPG, JPEG, PNG</strong>).</li>
                        <li><strong>Batas Ukuran Berkas:</strong> Maksimal <strong>5 MB</strong> per dokumen.</li>
                        <li><strong>Kualitas Dokumen:</strong> Pastikan hasil foto/scan dokumen KTP, KK, atau surat pendukung lainnya terlihat <strong>jelas, tidak buram, tidak terpotong, dan tidak silau</strong>.</li>
                        <li><strong>Tanda Wajib (*):</strong> Dokumen bertanda merah <span class="text-red-600 font-bold">*</span> wajib dilampirkan agar permohonan tidak ditolak saat verifikasi berkas oleh petugas Nagari.</li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($syaratBerlaku as $syarat)
                        @php
                            $existingDoc = $dokumenTersimpan[$syarat->id] ?? null;
                        @endphp
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="text-sm font-semibold text-slate-800 block">
                                        {{ $syarat->nama_dokumen }}
                                        @if ($syarat->wajib && ! $existingDoc)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </span>
                                    @if (! empty($syarat->keterangan))
                                        <span class="text-[11px] text-slate-500 block mt-0.5">{{ $syarat->keterangan }}</span>
                                    @endif
                                </div>
                                <span class="text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider shrink-0 {{ $existingDoc ? 'bg-emerald-100 text-emerald-800' : ($syarat->wajib ? 'bg-red-100 text-red-700' : 'bg-slate-200 text-slate-600') }}">
                                    {{ $existingDoc ? '✓ Tersedia' : ($syarat->wajib ? 'Wajib' : 'Opsional') }}
                                </span>
                            </div>

                            @if ($existingDoc)
                                <div class="p-3 bg-emerald-50/90 border border-emerald-300 rounded-xl space-y-1.5 shadow-2xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-900">
                                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                            <span>✓ Berkas Sudah Tersedia di Sistem</span>
                                        </div>
                                        <a href="{{ route('dokumen.warga', $existingDoc->id) }}" target="_blank" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-900 underline flex items-center gap-1 shrink-0">
                                            Lihat Berkas ↗
                                        </a>
                                    </div>
                                    <p class="text-[11px] text-emerald-800 leading-snug">
                                        Tersimpan dari pengajuan sebelumnya ({{ $existingDoc->uploaded_at?->format('d/m/Y') }}). Anda <strong>tidak perlu mengunggah ulang</strong> kecuali ingin mengganti dengan berkas baru.
                                    </p>
                                </div>
                            @endif

                            <div class="flex items-center justify-between gap-2 text-[11px] text-slate-500 font-medium">
                                <span class="text-[11px] text-slate-600 font-medium">
                                    {{ $existingDoc ? 'Pilih berkas baru jika ingin mengganti:' : 'Pilih berkas dokumen:' }}
                                </span>
                                <span class="px-2 py-0.5 rounded bg-white border border-slate-200">PDF / JPG / PNG • Maks. 5 MB</span>
                            </div>

                            <input 
                                type="file" 
                                accept=".jpg,.jpeg,.png,.pdf"
                                wire:model="berkasSyarat.{{ $syarat->id }}" 
                                class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition cursor-pointer"
                            >

                            <div wire:loading wire:target="berkasSyarat.{{ $syarat->id }}" class="text-[11px] text-emerald-600 flex items-center gap-1.5 font-medium">
                                <svg class="animate-spin h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Mengunggah berkas ke sistem...
                            </div>

                            @if (isset($berkasSyarat[$syarat->id]) && is_object($berkasSyarat[$syarat->id]))
                                <div class="flex items-center justify-between text-xs text-emerald-800 bg-emerald-50/90 px-3 py-2 rounded-lg border border-emerald-200">
                                    <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                        <span class="truncate font-medium">{{ $berkasSyarat[$syarat->id]->getClientOriginalName() }}</span>
                                    </div>
                                    <button 
                                        type="button" 
                                        wire:click="hapusBerkas({{ $syarat->id }})" 
                                        class="text-xs text-red-600 hover:text-red-800 font-semibold underline shrink-0 cursor-pointer"
                                        title="Hapus atau ganti berkas"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            @endif

                            @error("berkasSyarat.{$syarat->id}")
                                <span class="text-xs text-red-600 font-medium block">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- TOMBOL AJUKAN -->
        <div class="flex items-center justify-between pt-2">
            <a href="{{ url('/panel/pengajuan-wargas') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700 transition">
                &larr; Batalkan
            </a>
            <button 
                type="submit" 
                class="py-3 px-8 rounded-xl font-bold text-sm text-white bg-emerald-600 hover:bg-emerald-700 shadow-md transition flex items-center gap-2 cursor-pointer"
            >
                <span wire:loading.remove>Kirim Pengajuan Surat</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Memproses Pengajuan...
                </span>
            </button>
        </div>
    </form>
</div>
