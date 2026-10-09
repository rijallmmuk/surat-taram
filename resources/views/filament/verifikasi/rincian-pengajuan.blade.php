@php
    /** @var \App\Models\PengajuanSurat $record */
    $record = $record ?? $getRecord();
    $isWarga = auth()->user()?->role === 'warga';
    $penduduk = $record->pemohonSurat();
    $jenisSurat = $record->jenisSurat;
    $konfigurasiSnapshot = app(\App\Services\KonfigurasiSuratSnapshot::class);
    $skemaFields = $konfigurasiSnapshot->fieldUntuk($record)->filter(
        fn ($field) => app(\App\Services\KondisiFormEvaluator::class)->berlaku(
            $field->kondisi_tipe,
            $field->kondisi_kunci,
            $field->kondisi_nilai,
            $record->data_isian ?? [],
        ),
    );
    $syaratDokumens = $konfigurasiSnapshot->syaratUntuk($record)->filter(
        fn ($syarat) => app(\App\Services\SyaratDokumenApplicability::class)->berlaku($syarat, $record->data_isian ?? []),
    );
    $lampirans = $record->lampirans()->get();
    $dataIsian = $record->data_isian ?? [];
    $dokumenWargaService = app(\App\Services\DokumenWargaService::class);
    $richTextIsianService = app(\App\Services\RichTextIsianService::class);

    $formatRupiah = function ($val) {
        if (is_numeric($val)) {
            return 'Rp ' . number_format((float) $val, 0, ',', '.');
        }
        return $val;
    };

    $formatTanggalIndo = function ($val) {
        if (empty($val)) return '-';
        try {
            return \Carbon\Carbon::parse($val)->translatedFormat('d F Y');
        } catch (\Throwable $e) {
            return $val;
        }
    };

    $ungroupedFields = $skemaFields->filter(fn ($f) => empty($f->parent_group));
    $ungroupedNonTableFields = $ungroupedFields->filter(fn ($f) => $f->tipe_field !== 'table_repeater');
    $ungroupedTableFields = $ungroupedFields->filter(fn ($f) => $f->tipe_field === 'table_repeater');
    $groupedFields = $skemaFields->filter(fn ($f) => ! empty($f->parent_group))->groupBy('parent_group');

    // Pemetaan syarat dokumen dengan lampiran pengajuan
    $lampiranTercocok = [];
    $syaratStatus = [];

    foreach ($syaratDokumens as $syarat) {
        $matched = null;
        foreach ($lampirans as $lampiran) {
            if ($lampiran->nama_dokumen === $syarat->nama_dokumen || $dokumenWargaService->isSynonym($lampiran->nama_dokumen, $syarat->nama_dokumen)) {
                $matched = $lampiran;
                $lampiranTercocok[] = $lampiran->id;
                break;
            }
        }
        $hasFile = $matched && $dokumenWargaService->berkasFisikAda($matched->file_path);

        $syaratStatus[] = [
            'syarat' => $syarat,
            'lampiran' => $matched,
            'has_file' => $hasFile,
        ];
    }

    $lampiranLainnya = $lampirans->filter(fn ($l) => ! in_array($l->id, $lampiranTercocok, true));

    $getFileSize = function (?string $path) {
        if (! $path) return '-';
        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            $bytes = \Illuminate\Support\Facades\Storage::disk('local')->size($path);
            return $bytes > 1048576 
                ? round($bytes / 1048576, 2) . ' MB' 
                : round($bytes / 1024, 1) . ' KB';
        }
        return '-';
    };

    $jenisKelamin = ($penduduk?->jenis_kelamin === 'L' || $penduduk?->jenis_kelamin === 'Laki-Laki') 
        ? 'Laki-Laki' 
        : (($penduduk?->jenis_kelamin === 'P' || $penduduk?->jenis_kelamin === 'Perempuan') ? 'Perempuan' : ($penduduk?->jenis_kelamin ?? '-'));

    $ttl = ($penduduk?->tempat_lahir ? $penduduk->tempat_lahir . ', ' : '') . 
           ($penduduk?->tanggal_lahir ? \Carbon\Carbon::parse($penduduk->tanggal_lahir)->translatedFormat('d F Y') : '-');
    $usia = $penduduk?->tanggal_lahir ? '(' . \Carbon\Carbon::parse($penduduk->tanggal_lahir)->age . ' thn)' : '';

    $namaJorong = $penduduk?->jorong?->nama_jorong ?? 'Taram';
    $alamatTampil = $penduduk?->alamat ?: "Jorong {$namaJorong}, Nagari Taram";
@endphp

<div 
    x-data="{
        modalOpen: false,
        docTitle: '',
        docUrl: '',
        isPdf: false,
        zoom: 1,
        rotation: 0,
        openModal(title, url, pdf) {
            this.docTitle = title;
            this.docUrl = url;
            this.isPdf = pdf;
            this.zoom = 1;
            this.rotation = 0;
            this.modalOpen = true;
        },
        closeModal() {
            this.modalOpen = false;
            this.docUrl = '';
        },
        zoomIn() {
            if (this.zoom < 4) {
                this.zoom = +(this.zoom + 0.25).toFixed(2);
            }
        },
        zoomOut() {
            if (this.zoom > 0.3) {
                this.zoom = +(this.zoom - 0.25).toFixed(2);
            }
        },
        resetZoom() {
            this.zoom = 1;
            this.rotation = 0;
        },
        rotate() {
            this.rotation = (this.rotation + 90) % 360;
        }
    }"
    @keydown.escape.window="closeModal()"
    class="taram-pengajuan-detail min-w-0 space-y-4 sm:space-y-6"
>
    {{-- 1. DATA PEMOHON (KEPENDUDUKAN NAGARI TARAM) --}}
    <div class="taram-data-pemohon overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-200 bg-slate-50/80 px-4 py-3.5 dark:border-gray-800 dark:bg-gray-800/60 sm:px-5">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                {{ $isWarga ? '1. Data Diri Pemohon' : '1. Data Pemohon (Kependudukan Nagari Taram)' }}
            </h3>
            <span class="text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                {{ $isWarga ? 'Data resmi Nagari' : 'Terverifikasi Master Penduduk' }}
            </span>
        </div>

        {{-- Berderet ke bawah dengan 2 kolom, tanpa tabel --}}
        <div class="grid grid-cols-1 gap-x-10 gap-y-1 p-4 md:grid-cols-2 sm:p-5">
            {{-- Kolom Kiri --}}
            <div class="divide-y divide-slate-100 dark:divide-gray-800">
                {{-- Nama Lengkap --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Nama Lengkap</span>
                    <span class="text-xs font-bold text-slate-900 dark:text-white text-right">{{ $penduduk?->nama ?? '-' }}</span>
                </div>

                {{-- NIK --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Nomor Induk Kependudukan (NIK)</span>
                    <span class="text-xs font-bold font-mono text-slate-900 dark:text-white text-right">{{ $record->penduduk_nik }}</span>
                </div>

                {{-- Nomor KK --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Nomor Kartu Keluarga (KK)</span>
                    <span class="text-xs font-semibold font-mono text-slate-800 dark:text-slate-200 text-right">{{ $penduduk?->kk_number ?: '-' }}</span>
                </div>

                {{-- Jenis Kelamin --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Jenis Kelamin</span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $jenisKelamin }}</span>
                </div>

                {{-- Tempat, Tanggal Lahir --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Tempat, Tanggal Lahir</span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 text-right">
                        {{ $ttl }} <span class="text-slate-400">{{ $usia }}</span>
                    </span>
                </div>

                {{-- Alamat --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Alamat</span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $alamatTampil }}</span>
                </div>
            </div>

            {{-- Kolom Kanan --}}
            <div class="divide-y divide-slate-100 dark:divide-gray-800">
                {{-- Agama --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Agama</span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $penduduk?->agama?->nama ?: '-' }}</span>
                </div>

                {{-- Status Kawin --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Status Kawin</span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $penduduk?->statusKawin?->nama ?: '-' }}</span>
                </div>

                {{-- Pekerjaan --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Pekerjaan</span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $penduduk?->pekerjaan?->nama ?: '-' }}</span>
                </div>

                {{-- Pendidikan Terakhir --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Pendidikan Terakhir</span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $penduduk?->pendidikan?->nama ?: '-' }}</span>
                </div>

                {{-- Kewarganegaraan --}}
                <div class="py-2.5 flex items-start justify-between gap-4">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0 w-36">Kewarganegaraan</span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 text-right">{{ $penduduk?->kewarganegaraan?->nama ?: 'WNI' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. RINCIAN FORMULIR KETERANGAN KHUSUS SURAT --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3.5 dark:border-gray-800 dark:bg-gray-800/60 sm:px-5">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                2. Rincian Formulir Keterangan Khusus Surat
            </h3>
        </div>

        <div class="p-4 sm:p-5">
            @if (empty($dataIsian) && $skemaFields->isEmpty())
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-gray-800/40 border border-slate-200/80 dark:border-gray-700 text-xs text-slate-500 dark:text-slate-400">
                    Surat ini tidak memerlukan isian keterangan tambahan khusus (cukup menggunakan data kependudukan resmi Nagari Taram).
                </div>
            @else
                <div class="space-y-4">
                    {{-- Field Satuan (Non-Tabel) --}}
                    @if ($ungroupedNonTableFields->isNotEmpty())
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($ungroupedNonTableFields as $field)
                                @php
                                    $val = $dataIsian[$field->nama_field] ?? null;
                                    $fieldNameLower = strtolower($field->nama_field);
                                    $isTextarea = in_array($field->tipe_field, ['textarea', 'rich_text'], true);
                                    $fileLampiran = $field->tipe_field === 'file' && is_string($val)
                                        ? $lampirans->firstWhere('file_path', $val)
                                        : null;
                                @endphp
                                <div class="min-w-0 break-words rounded-lg border border-slate-100 bg-slate-50/80 p-3.5 dark:border-gray-800/80 dark:bg-gray-800/50 {{ $isTextarea ? 'md:col-span-2 xl:col-span-3' : '' }}">
                                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                        {{ $field->label }}
                                    </span>
                                    <div class="mt-1 min-w-0 break-words text-sm font-semibold text-slate-900 dark:text-white">
                                        @if ($field->tipe_field === 'file')
                                            @if ($fileLampiran)
                                                <a href="{{ route('dokumen.lampiran', $fileLampiran) }}" target="_blank" rel="noopener noreferrer" class="font-medium text-emerald-700 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">Lihat berkas</a>
                                            @else
                                                -
                                            @endif
                                        @elseif ($field->tipe_field === 'rich_text')
                                            <div class="font-normal leading-relaxed">{!! $richTextIsianService->display($val) !!}</div>
                                        @elseif ($field->tipe_field === 'date')
                                            {{ $formatTanggalIndo($val) }}
                                        @elseif (str_contains($fieldNameLower, 'penghasilan') || str_contains($fieldNameLower, 'nominal') || str_contains($fieldNameLower, 'gaji'))
                                            <span class="text-emerald-700 dark:text-emerald-400 font-bold font-mono">{{ $formatRupiah($val) }}</span>
                                        @elseif ($isTextarea)
                                            <div class="whitespace-pre-line leading-relaxed font-normal bg-white dark:bg-gray-900 p-3 rounded border border-slate-200 dark:border-gray-700 mt-1">
                                                {{ $val ?: '-' }}
                                            </div>
                                        @else
                                            {{ $val !== null && $val !== '' ? $val : '-' }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Field Tabel Repeater --}}
                    @if ($ungroupedTableFields->isNotEmpty())
                        @foreach ($ungroupedTableFields as $tField)
                            @php
                                $tVal = $dataIsian[$tField->nama_field] ?? [];
                                $rows = is_array($tVal) ? $tVal : [];
                                $koloms = $tField->kolomTabels->sortBy('urutan');
                            @endphp
                            <div class="mt-4 pt-2">
                                <span class="block text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-2">
                                    {{ $tField->label }}
                                </span>
                                @if (empty($rows))
                                    <p class="text-xs text-slate-400 italic p-3 rounded bg-slate-50 dark:bg-gray-800 border border-slate-200 dark:border-gray-700">
                                        (Tidak ada rincian data baris tabel yang dimasukkan)
                                    </p>
                                @else
                                    <div class="rounded-lg border border-slate-200 dark:border-gray-700">
                                        <table class="taram-review-table w-full border-collapse text-left text-xs">
                                            <thead>
                                                <tr class="bg-slate-100/75 dark:bg-gray-800 text-slate-600 dark:text-slate-300 font-semibold border-b border-slate-200 dark:border-gray-700">
                                                    <th class="py-2.5 px-3 w-12 text-center" style="width: 48px; text-align: center;">No</th>
                                                    @foreach ($koloms as $kol)
                                                        <th class="py-2.5 px-3 font-bold">{{ $kol->label }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
                                                @foreach ($rows as $rIdx => $row)
                                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-gray-800/40">
                                                        <td data-label="Baris" class="py-2.5 px-3 text-center font-medium text-slate-500">{{ $rIdx + 1 }}</td>
                                                        @foreach ($koloms as $kol)
                                                            <td data-label="{{ $kol->label }}" class="break-words px-3 py-2.5 font-medium text-slate-800 dark:text-slate-200">
                                                                {{ $row[$kol->nama_kolom] ?? '-' }}
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endif

                    {{-- Grup Data Tambahan (Data Orang Tua, dll) --}}
                    @if ($groupedFields->isNotEmpty())
                        <div class="space-y-4 pt-2">
                            @foreach ($groupedFields as $groupKey => $fieldsInGroup)
                                @php
                                    $isOptional = (bool) $fieldsInGroup->first()->is_optional_group;
                                    $groupTitle = match ($groupKey) {
                                        'data_ayah' => 'Data Ayah Kandung',
                                        'data_ibu' => 'Data Ibu Kandung',
                                        default => ucwords(str_replace('_', ' ', $groupKey)),
                                    };
                                    $isIncluded = ! $isOptional || ! empty($dataIsian['sertakan_' . $groupKey]);
                                @endphp

                                <div class="rounded-lg border border-slate-200 dark:border-gray-800 p-4 bg-slate-50/50 dark:bg-gray-800/30">
                                    <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-gray-800 pb-2 mb-3">
                                        <span class="font-bold text-xs text-slate-800 dark:text-slate-200">{{ $groupTitle }}</span>
                                        @if ($isOptional)
                                            <span class="text-[10px] px-2 py-0.5 rounded font-medium {{ $isIncluded ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-gray-800' }}">
                                                {{ $isIncluded ? '✓ Disertakan Pemohon' : 'Tidak Disertakan' }}
                                            </span>
                                        @endif
                                    </div>

                                    @if (! $isIncluded)
                                        <p class="text-xs text-slate-400 italic">
                                            Pemohon memilih untuk tidak menyertakan rincian {{ strtolower($groupTitle) }}.
                                        </p>
                                    @else
                                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                                            @foreach ($fieldsInGroup as $f)
                                                @php
                                                    $fVal = $dataIsian[$f->nama_field] ?? null;
                                                    $fLower = strtolower($f->nama_field);
                                                    $fileLampiran = $f->tipe_field === 'file' && is_string($fVal)
                                                        ? $lampirans->firstWhere('file_path', $fVal)
                                                        : null;
                                                @endphp
                                                <div class="min-w-0 break-words rounded border border-slate-100 bg-white p-2.5 dark:border-gray-700 dark:bg-gray-800">
                                                    <span class="text-slate-400 block text-[10px] font-semibold uppercase">{{ $f->label }}</span>
                                                    <div class="font-semibold text-slate-800 dark:text-slate-100 text-xs mt-1">
                                                        @if ($f->tipe_field === 'file')
                                                            @if ($fileLampiran)
                                                                <a href="{{ route('dokumen.lampiran', $fileLampiran) }}" target="_blank" rel="noopener noreferrer" class="text-emerald-700 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">Lihat berkas</a>
                                                            @else
                                                                -
                                                            @endif
                                                        @elseif ($f->tipe_field === 'rich_text')
                                                            <div class="font-normal leading-relaxed">{!! $richTextIsianService->display($fVal) !!}</div>
                                                        @elseif ($f->tipe_field === 'date')
                                                            {{ $formatTanggalIndo($fVal) }}
                                                        @elseif (str_contains($fLower, 'penghasilan') || str_contains($fLower, 'gaji'))
                                                            {{ $formatRupiah($fVal) }}
                                                        @else
                                                            {{ $fVal !== null && $fVal !== '' ? $fVal : '-' }}
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- 3. BERKAS PERSYARATAN & DOKUMEN LAMPIRAN PEMOHON --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-200 bg-slate-50/80 px-4 py-3.5 dark:border-gray-800 dark:bg-gray-800/60 sm:px-5">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                3. Berkas Persyaratan & Dokumen Lampiran Pemohon
            </h3>
            <span class="text-xs text-slate-600 dark:text-slate-300">
                {{ $isWarga ? 'Ketuk dokumen untuk melihatnya lebih jelas' : 'Ketuk dokumen untuk memperbesar dan memeriksa keabsahan' }}
            </span>
        </div>

        <div class="p-4 sm:p-5">
            @if ($lampirans->isEmpty() && $syaratDokumens->isNotEmpty())
                <p class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-200">Tidak ada berkas yang dilampirkan pada pengajuan ini.</p>
            @endif
            @if ($syaratDokumens->isEmpty() && $lampirans->isEmpty())
                <p class="text-xs text-slate-400 italic py-2">
                    Tidak ada berkas yang dilampirkan pada pengajuan ini.
                </p>
            @else
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-5">
                    @foreach ($syaratStatus as $idx => $item)
                        @php
                            $syarat = $item['syarat'];
                            $lampiran = $item['lampiran'];
                            $filePath = $lampiran?->file_path;
                            $hasFile = $item['has_file'];
                            $isFromBank = $hasFile && \App\Models\DokumenWarga::where('penduduk_nik', $record->penduduk_nik)->where('file_path', $filePath)->exists();
                            $url = $hasFile ? route('dokumen.lampiran', $lampiran->id) : null;
                            $isPdf = $hasFile && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf';
                            $viewerTitle = $syarat->nama_dokumen.' — '.($penduduk?->nama ?? $record->penduduk_nik);
                        @endphp

                        <div class="rounded-xl border border-slate-200 dark:border-gray-800 bg-slate-50/40 dark:bg-gray-800/30 overflow-hidden flex flex-col justify-between hover:border-slate-300 dark:hover:border-gray-700 transition shadow-2xs">
                            {{-- Card Header --}}
                            <div class="p-3.5 bg-white dark:bg-gray-900 border-b border-slate-200/80 dark:border-gray-800 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-bold text-xs text-slate-900 dark:text-white flex items-center gap-1">
                                        <span>{{ $syarat->nama_dokumen }}</span>
                                        @if ($syarat->wajib)
                                            <span class="text-rose-500 font-bold">*</span>
                                        @endif
                                    </div>
                                    @if (! empty($syarat->keterangan))
                                        <span class="mt-0.5 block break-words text-[11px] text-slate-600 dark:text-slate-300">{{ $syarat->keterangan }}</span>
                                    @endif
                                </div>
                                <span class="text-[10px] px-2 py-0.5 rounded font-bold uppercase shrink-0 {{ $syarat->wajib ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-slate-100 text-slate-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                    {{ $syarat->wajib ? 'WAJIB' : 'OPSIONAL' }}
                                </span>
                            </div>

                            {{-- Direct Preview Area ("langsung tampil semua, apapun itu") --}}
                            <div class="p-3 flex-1 flex flex-col justify-center">
                                @if ($hasFile)
                                    <div 
                                        @click="openModal(@js($viewerTitle), @js($url), {{ $isPdf ? 'true' : 'false' }})"
                                        @keydown.enter.prevent="openModal(@js($viewerTitle), @js($url), {{ $isPdf ? 'true' : 'false' }})"
                                        @keydown.space.prevent="openModal(@js($viewerTitle), @js($url), {{ $isPdf ? 'true' : 'false' }})"
                                        role="button"
                                        tabindex="0"
                                        aria-label="Perbesar {{ $syarat->nama_dokumen }}"
                                        class="group relative flex h-48 w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-slate-200/90 bg-slate-950/5 shadow-2xs transition hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-gray-800 dark:bg-gray-950 sm:h-60"
                                        title="Klik untuk memperbesar dokumen pada modal"
                                    >
                                        @if ($isPdf)
                                            {{-- PDF Preview iframe --}}
                                            <iframe 
                                                src="{{ $url }}#toolbar=0&navpanes=0&scrollbar=0" 
                                                class="w-full h-full pointer-events-none rounded"
                                            ></iframe>
                                            <div class="absolute inset-0 bg-slate-900/20 group-hover:bg-slate-900/40 transition flex flex-col items-center justify-center gap-1.5 p-3 text-center">
                                                <span class="p-2.5 rounded-full bg-white/95 dark:bg-gray-800/95 text-slate-800 dark:text-slate-200 shadow-md group-hover:scale-110 transition">
                                                    <svg class="w-6 h-6 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span class="text-xs font-bold text-white bg-slate-900/85 backdrop-blur-xs px-3 py-1 rounded-md shadow">
                                                    🔍 Klik untuk Perbesar PDF
                                                </span>
                                            </div>
                                        @else
                                            {{-- Image Preview img --}}
                                            <img 
                                                src="{{ $url }}" 
                                                alt="{{ $syarat->nama_dokumen }}"
                                                class="w-full h-full object-contain group-hover:scale-105 transition duration-200"
                                                loading="lazy"
                                            />
                                            <div class="absolute inset-0 bg-slate-900/25 opacity-0 group-hover:opacity-100 transition duration-150 flex items-center justify-center">
                                                <span class="text-xs font-bold text-white bg-slate-900/90 backdrop-blur-xs px-3.5 py-1.5 rounded-lg shadow-lg flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6" />
                                                    </svg>
                                                    Klik untuk Perbesar & Periksa
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="w-full h-60 rounded-lg border-2 border-dashed border-slate-200 dark:border-gray-800 flex flex-col items-center justify-center p-4 text-center bg-white/50 dark:bg-gray-900/40">
                                        <svg class="w-8 h-8 text-slate-300 dark:text-slate-600 mb-1.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        <span class="text-xs font-semibold {{ $syarat->wajib ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                                            {{ $syarat->wajib ? 'Belum Dilampirkan (Syarat Wajib)' : 'Tidak Dilampirkan (Opsional)' }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            {{-- Card Footer --}}
                            <div class="taram-review-file-actions flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/80 bg-white px-3.5 py-2.5 text-xs dark:border-gray-800 dark:bg-gray-900">
                                @if ($hasFile)
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-emerald-600 dark:text-emerald-400 font-bold text-[11px]">✓ Berkas Terlampir</span>
                                            <span class="text-slate-400 text-[10px]">({{ $getFileSize($filePath) }})</span>
                                        </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button 
                                            type="button"
                                            @click="openModal(@js($viewerTitle), @js($url), {{ $isPdf ? 'true' : 'false' }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-gray-800 dark:text-slate-200 font-semibold text-[11px] transition cursor-pointer"
                                            title="Perbesar di Modal"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6" />
                                            </svg>
                                            <span>Perbesar</span>
                                        </button>

                                        <a 
                                            href="{{ $url }}" 
                                            target="_blank"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 font-semibold text-[11px] border border-emerald-200 dark:border-emerald-800 transition"
                                            title="Buka Tab Baru"
                                        >
                                            <span>Buka Berkas di Tab Baru ↗</span>
                                        </a>
                                    </div>
                                @else
                                    <span class="text-slate-400 text-[11px] italic">Tidak ada berkas fisik</span>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{-- Lampiran Tambahan Jika Ada --}}
                    @foreach ($lampiranLainnya as $extraIdx => $extra)
                        @php
                            $filePath = $extra->file_path;
                            $hasFile = app(\App\Services\DokumenWargaService::class)->berkasFisikAda($filePath);
                            $url = $hasFile ? route('dokumen.lampiran', $extra->id) : null;
                            $isPdf = $hasFile && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf';
                            $viewerTitle = $extra->nama_dokumen.' — '.($penduduk?->nama ?? $record->penduduk_nik);
                        @endphp

                        <div class="rounded-xl border border-slate-200 dark:border-gray-800 bg-slate-50/40 dark:bg-gray-800/30 overflow-hidden flex flex-col justify-between hover:border-slate-300 dark:hover:border-gray-700 transition shadow-2xs">
                            <div class="p-3.5 bg-white dark:bg-gray-900 border-b border-slate-200/80 dark:border-gray-800 flex items-start justify-between gap-3">
                                <div class="font-bold text-xs text-slate-900 dark:text-white">
                                    {{ $extra->nama_dokumen }}
                                </div>
                                <span class="text-[10px] px-2 py-0.5 rounded font-bold uppercase bg-slate-100 text-slate-600 dark:bg-gray-800 dark:text-slate-400">
                                    TAMBAHAN
                                </span>
                            </div>

                            <div class="p-3 flex-1 flex flex-col justify-center">
                                @if ($hasFile)
                                    <div 
                                        @click="openModal(@js($viewerTitle), @js($url), {{ $isPdf ? 'true' : 'false' }})"
                                        @keydown.enter.prevent="openModal(@js($viewerTitle), @js($url), {{ $isPdf ? 'true' : 'false' }})"
                                        @keydown.space.prevent="openModal(@js($viewerTitle), @js($url), {{ $isPdf ? 'true' : 'false' }})"
                                        role="button"
                                        tabindex="0"
                                        aria-label="Perbesar {{ $extra->nama_dokumen }}"
                                        class="group relative flex h-48 w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-slate-200/90 bg-slate-950/5 shadow-2xs transition hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-gray-800 dark:bg-gray-950 sm:h-60"
                                        title="Klik untuk memperbesar dokumen pada modal"
                                    >
                                        @if ($isPdf)
                                            <iframe 
                                                src="{{ $url }}#toolbar=0&navpanes=0&scrollbar=0" 
                                                class="w-full h-full pointer-events-none rounded"
                                            ></iframe>
                                            <div class="absolute inset-0 bg-slate-900/20 group-hover:bg-slate-900/40 transition flex flex-col items-center justify-center gap-1.5 p-3 text-center">
                                                <span class="p-2.5 rounded-full bg-white/95 dark:bg-gray-800/95 text-slate-800 dark:text-slate-200 shadow-md group-hover:scale-110 transition">
                                                    <svg class="w-6 h-6 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span class="text-xs font-bold text-white bg-slate-900/85 backdrop-blur-xs px-3 py-1 rounded-md shadow">
                                                    🔍 Klik untuk Perbesar PDF
                                                </span>
                                            </div>
                                        @else
                                            <img 
                                                src="{{ $url }}" 
                                                alt="{{ $extra->nama_dokumen }}"
                                                class="w-full h-full object-contain group-hover:scale-105 transition duration-200"
                                                loading="lazy"
                                            />
                                            <div class="absolute inset-0 bg-slate-900/25 opacity-0 group-hover:opacity-100 transition duration-150 flex items-center justify-center">
                                                <span class="text-xs font-bold text-white bg-slate-900/90 backdrop-blur-xs px-3.5 py-1.5 rounded-lg shadow-lg flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6" />
                                                    </svg>
                                                    Klik untuk Perbesar & Periksa
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="w-full h-60 rounded-lg border-2 border-dashed border-slate-200 dark:border-gray-800 flex items-center justify-center p-4 text-center">
                                        <span class="text-xs text-slate-400 italic">Berkas tidak tersedia</span>
                                    </div>
                                @endif
                            </div>

                            <div class="taram-review-file-actions flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/80 bg-white px-3.5 py-2.5 text-xs dark:border-gray-800 dark:bg-gray-900">
                                @if ($hasFile)
                                    <div class="min-w-0">
                                        <span class="text-emerald-600 dark:text-emerald-400 font-bold text-[11px]">✓ Berkas Terlampir</span>
                                        <span class="text-slate-400 text-[10px] block">({{ $getFileSize($filePath) }})</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button 
                                            type="button"
                                            @click="openModal(@js($viewerTitle), @js($url), {{ $isPdf ? 'true' : 'false' }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-gray-800 dark:text-slate-200 font-semibold text-[11px] transition cursor-pointer"
                                        >
                                            <span>Perbesar</span>
                                        </button>
                                        <a 
                                            href="{{ $url }}" 
                                            target="_blank"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-gray-800 dark:text-slate-200 font-semibold text-[11px] transition"
                                        >
                                            <span>Buka Berkas di Tab Baru ↗</span>
                                        </a>
                                    </div>
                                @else
                                    <span class="text-slate-400 text-[11px] italic">Tidak ada berkas fisik</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- MODAL PREVIEW & ZOOM DOKUMEN --}}
    <div 
        x-show="modalOpen" 
        x-cloak
        style="display: none;"
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog" 
        aria-modal="true"
        aria-label="Pratinjau dokumen"
    >
        <!-- Backdrop -->
        <div 
            x-show="modalOpen" 
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/80 backdrop-blur-xs transition-opacity"
            @click="closeModal()"
        ></div>

        <!-- Modal Container -->
        <div class="fixed inset-0 z-10 flex flex-col items-center justify-center p-3 sm:p-6 pointer-events-none">
            <div 
                x-show="modalOpen" 
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="pointer-events-auto flex h-[90dvh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-700/80 bg-slate-900 text-white shadow-2xl sm:h-[88dvh] sm:rounded-2xl"
                @click.stop
            >
                <!-- Modal Header -->
                <div class="flex shrink-0 flex-col gap-2 border-b border-slate-700 bg-slate-800/90 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:px-5 sm:py-3.5">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="p-1.5 rounded-lg bg-emerald-500/20 text-emerald-400 shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </span>
                        <h4 class="text-sm font-bold text-white truncate" x-text="docTitle"></h4>
                    </div>

                    <!-- Controls: Zoom In, Zoom Out, Reset, Rotate, Open in Tab, Close -->
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-if="!isPdf">
                            <div class="flex items-center gap-1 bg-slate-700/70 p-1 rounded-lg border border-slate-600/60">
                                {{-- Zoom Out / Perkecil --}}
                                <button 
                                    type="button" 
                                    @click="zoomOut()" 
                                    title="Perkecil (-)"
                                    aria-label="Perkecil gambar"
                                    class="flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded text-slate-200 transition hover:bg-slate-600 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                                    </svg>
                                </button>

                                {{-- Zoom Level & Reset --}}
                                <button 
                                    type="button" 
                                    @click="resetZoom()" 
                                    title="Klik untuk Reset ke 100%"
                                    aria-label="Kembalikan ukuran gambar ke 100 persen"
                                    class="min-h-11 rounded px-2 text-xs font-mono font-semibold text-slate-200 transition hover:bg-slate-600 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                                    x-text="Math.round(zoom * 100) + '%'"
                                >
                                </button>

                                {{-- Zoom In / Perbesar --}}
                                <button 
                                    type="button" 
                                    @click="zoomIn()" 
                                    title="Perbesar (+)"
                                    aria-label="Perbesar gambar"
                                    class="flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded text-slate-200 transition hover:bg-slate-600 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>

                                {{-- Putar / Rotate --}}
                                <button 
                                    type="button" 
                                    @click="rotate()" 
                                    title="Putar 90°"
                                    aria-label="Putar gambar 90 derajat"
                                    class="flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded text-slate-200 transition hover:bg-slate-600 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                </button>
                            </div>
                        </template>

                        {{-- Open in new tab --}}
                        <a 
                            :href="docUrl" 
                            target="_blank" 
                            title="Buka Dokumen Asli di Tab Baru"
                            aria-label="Buka dokumen asli di tab baru"
                            rel="noopener noreferrer"
                            class="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-300 transition hover:bg-slate-700/80 hover:text-white focus-visible:outline-2 focus-visible:outline-white"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>

                        {{-- Close Button --}}
                        <button 
                            type="button" 
                            @click="closeModal()" 
                            class="flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-lg text-slate-300 transition hover:bg-rose-500/20 hover:text-rose-300 focus-visible:outline-2 focus-visible:outline-white"
                            title="Tutup (Esc)"
                            aria-label="Tutup pratinjau dokumen"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Content (Viewer Area) -->
                <div class="flex-1 overflow-auto bg-slate-950 flex items-center justify-center p-4 relative select-none">
                    <!-- If Image -->
                    <template x-if="!isPdf && docUrl">
                        <div class="w-full h-full flex items-center justify-center overflow-auto p-2">
                            <img 
                                :src="docUrl" 
                                :alt="docTitle"
                                class="max-w-full max-h-full object-contain rounded shadow-lg transition-transform duration-100 ease-out cursor-zoom-in"
                                :style="`transform: scale(${zoom}) rotate(${rotation}deg); transform-origin: center center;`"
                                @click="zoom < 2.5 ? zoomIn() : resetZoom()"
                            />
                        </div>
                    </template>

                    <!-- If PDF -->
                    <template x-if="isPdf && docUrl">
                        <iframe 
                            :src="docUrl" 
                            class="w-full h-full rounded border-0 bg-white"
                        ></iframe>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
