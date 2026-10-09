<?php

namespace App\Filament\Resources\JenisSurats\Schemas;

use App\Models\JenisSurat;
use App\Models\MasterSyaratDokumen;
use App\Models\SkemaFormField;
use App\Services\CaraMenjawab;
use App\Services\KatalogTagSurat;
use App\Services\KesiapanJenisSurat;
use App\Services\KodeIsian;
use App\Services\MasterReferensiHelper;
use App\Services\NomorSuratFormatter;
use App\Services\PenyelarasIsiSurat;
use App\Services\PenyusunSurat;
use App\Services\TemplatSurat;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class JenisSuratForm
{
    /** @return array<int, array<string, mixed>> */
    private static function currentFieldDefinitions(Get $get, ?Model $record = null): array
    {
        $fields = $get('/skemaFormFields') ?: ($get('../../skemaFormFields') ?: $get('skemaFormFields'));
        if (is_array($fields)) {
            return array_values(array_filter($fields, 'is_array'));
        }

        $jenisSurat = $record instanceof JenisSurat ? $record : $record?->jenisSurat;

        return $jenisSurat instanceof JenisSurat
            ? $jenisSurat->skemaFormFields()->get()->map(fn (SkemaFormField $field): array => $field->toArray())->all()
            : [];
    }

    /** @return array<string, string> */
    public static function conditionKeys(Get $get, ?Model $record = null): array
    {
        $options = [];
        foreach (self::currentFieldDefinitions($get, $record) as $field) {
            if ($get('kondisi_tipe') === 'kelompok' && ! empty($field['is_optional_group']) && filled($field['parent_group'] ?? null)) {
                $key = Str::snake((string) $field['parent_group']);
                $options[$key] = ucwords(str_replace('_', ' ', $key));
            }

            if ($get('kondisi_tipe') === 'pilihan'
                && ($field['tipe_field'] ?? null) === 'select'
                && filled($field['nama_field'] ?? null)
                && $field['nama_field'] !== $get('nama_field')) {
                $options[$field['nama_field']] = $field['label'] ?? $field['nama_field'];
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function conditionValues(Get $get, ?Model $record = null): array
    {
        foreach (self::currentFieldDefinitions($get, $record) as $field) {
            if (($field['nama_field'] ?? null) === $get('kondisi_kunci')) {
                return MasterReferensiHelper::getOptionsForField(
                    $field['referensi_master'] ?? null,
                    (string) $field['nama_field'],
                    $field['opsi_pilihan'] ?? null,
                );
            }
        }

        return [];
    }

    /** @return array<string, string> */
    public static function fieldConditionKeys(Get $get, ?Model $record = null): array
    {
        $fields = collect(self::currentFieldDefinitions($get, $record));
        $optionalGroups = $fields
            ->filter(fn (array $field): bool => ! empty($field['is_optional_group']) && filled($field['parent_group'] ?? null))
            ->map(fn (array $field): string => Str::snake((string) $field['parent_group']))
            ->all();

        return $fields
            ->filter(fn (array $field): bool => ($field['tipe_field'] ?? null) === 'select'
                && ($field['kondisi_tipe'] ?? 'selalu') === 'selalu'
                && ! in_array(Str::snake((string) ($field['parent_group'] ?? '')), $optionalGroups, true)
                && filled($field['nama_field'] ?? null)
                && $field['nama_field'] !== $get('nama_field'))
            ->mapWithKeys(fn (array $field): array => [$field['nama_field'] => $field['label'] ?? $field['nama_field']])
            ->all();
    }

    /**
     * Menghasilkan daftar saran kelompok data yang cerdas dan dinamis:
     * Menggabungkan preset umum nagari, kelompok yang tersimpan di database,
     * serta isian kelompok yang sedang diketik manual oleh admin pada form aktif.
     *
     * @return array<string>
     */
    public static function getParentGroupSuggestions(Get $get, ?Model $record = null): array
    {
        $defaultGroups = [
            'Data Ayah',
            'Data Ibu',
            'Data Suami / Istri',
            'Data Jenazah',
            'Data Saksi I',
            'Data Saksi II',
            'Data Usaha',
            'Data Ahli Waris',
            'Data Wali',
        ];

        $dynamicGroups = [];

        // 1. Ambil dari seluruh field pada form yang sedang aktif (termasuk isian yang baru saja diketik)
        $formFields = $get('/skemaFormFields') ?: ($get('../../skemaFormFields') ?: $get('skemaFormFields'));
        if (is_array($formFields)) {
            foreach ($formFields as $item) {
                $groupVal = is_array($item) ? ($item['parent_group'] ?? null) : ($item->parent_group ?? null);
                if (filled($groupVal)) {
                    $cleaned = trim((string) $groupVal);
                    $dynamicGroups[] = Str::contains($cleaned, '_') ? ucwords(str_replace('_', ' ', $cleaned)) : $cleaned;
                }
            }
        }

        // 2. Ambil dari database record jenis surat yang sedang diedit
        $jenisSurat = null;
        if ($record instanceof JenisSurat) {
            $jenisSurat = $record;
        } elseif ($record instanceof SkemaFormField) {
            $jenisSurat = $record->jenisSurat;
        }

        if ($jenisSurat && $jenisSurat->exists) {
            $savedGroups = $jenisSurat->skemaFormFields()
                ->whereNotNull('parent_group')
                ->where('parent_group', '!=', '')
                ->pluck('parent_group')
                ->map(fn ($g) => ucwords(str_replace('_', ' ', (string) $g)))
                ->toArray();
            $dynamicGroups = array_merge($dynamicGroups, $savedGroups);
        }

        // 3. Ambil dari riwayat kelompok yang pernah ada di database nagari (di-cache 5 menit)
        try {
            $dbGroups = Cache::remember('builder_parent_groups_db', 300, function () {
                return SkemaFormField::query()
                    ->whereNotNull('parent_group')
                    ->where('parent_group', '!=', '')
                    ->distinct()
                    ->limit(30)
                    ->pluck('parent_group')
                    ->map(fn ($g) => ucwords(str_replace('_', ' ', (string) $g)))
                    ->toArray();
            });
            $dynamicGroups = array_merge($dynamicGroups, $dbGroups);
        } catch (\Throwable) {
            // Abaikan jika tabel belum siap
        }

        return array_values(array_unique(array_filter(array_merge($defaultGroups, $dynamicGroups))));
    }

    /**
     * Lengkapi kode pertanyaan/kolom yang belum ada dan selaraskan isi surat dengan pertanyaan.
     * Dipanggil sebelum menyimpan, sehingga isi surat selalu memuat setiap jawaban yang harus dicetak.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function denganIsiSuratSelaras(array $data): array
    {
        $pertanyaan = is_array($data['skemaFormFields'] ?? null) ? $data['skemaFormFields'] : [];
        $kodePertanyaan = collect($pertanyaan)->pluck('nama_field')->filter()->all();
        foreach ($pertanyaan as $kunci => $field) {
            if (! is_array($field)) {
                continue;
            }
            if (blank($field['nama_field'] ?? null) && filled($field['label'] ?? null)) {
                $field['nama_field'] = $kodePertanyaan[] = KodeIsian::buat($field['label'], $kodePertanyaan);
            }

            $kolom = is_array($field['kolomTabels'] ?? null) ? $field['kolomTabels'] : [];
            $kodeKolom = collect($kolom)->pluck('nama_kolom')->filter()->all();
            foreach ($kolom as $kunciKolom => $item) {
                if (is_array($item) && blank($item['nama_kolom'] ?? null) && filled($item['label'] ?? null)) {
                    $kolom[$kunciKolom]['nama_kolom'] = $kodeKolom[] = KodeIsian::buat($item['label'], $kodeKolom, 'kolom');
                }
            }
            if ($kolom !== []) {
                $field['kolomTabels'] = $kolom;
            }

            $pertanyaan[$kunci] = $field;
        }
        $data['skemaFormFields'] = $pertanyaan;

        // Filament menjalankan hook daftar pertanyaan sebelum hook "Cara menjawab", jadi tipe dibaca dari pilihan admin.
        // Pertanyaan yang belum selesai diisi tetap dikenali, tetapi belum dimasukkan ke surat.
        $skemaSurat = collect($pertanyaan)->filter(fn (mixed $field): bool => is_array($field))
            ->map(function (array $field): array {
                if ($cara = CaraMenjawab::dari($field['cara_menjawab'] ?? null)) {
                    $field['tipe_field'] = $cara['tipe'];
                }
                if (blank($field['label'] ?? null) || blank($field['tipe_field'] ?? null)) {
                    $field['hanya_pemeriksaan'] = true;
                }
                $field['kolomTabels'] = collect(is_array($field['kolomTabels'] ?? null) ? $field['kolomTabels'] : [])
                    ->map(function (mixed $kolom): mixed {
                        if (is_array($kolom) && ($cara = CaraMenjawab::dari($kolom['cara_mengisi'] ?? null))) {
                            $kolom['tipe_kolom'] = $cara['tipe'];
                        }

                        return $kolom;
                    })
                    ->filter(fn (mixed $kolom): bool => is_array($kolom) && filled($kolom['label'] ?? null) && filled($kolom['tipe_kolom'] ?? null))
                    ->all();

                return $field;
            })
            ->all();

        foreach ((array) ($data['templateSurats'] ?? []) as $kunci => $template) {
            $konten = is_array($template) ? ($template['konten'] ?? null) : null;
            $data['templateSurats'][$kunci]['konten'] = app(PenyelarasIsiSurat::class)
                ->selaraskan(is_string($konten) ? json_decode($konten, true) : $konten, $skemaSurat);

            break;
        }

        return $data;
    }

    /**
     * Pilihan "Cara menjawab" untuk pertanyaan atau kolom; tipe dan format isian tersimpan di field tersembunyi.
     *
     * @param  array<string, string>  $pilihan
     */
    private static function isianCaraMenjawab(string $nama, string $label, string $kolomTipe, array $pilihan): Select
    {
        return Select::make($nama)
            ->label($label)
            ->options($pilihan)
            ->afterStateHydrated(fn (Set $set, Get $get, ?Model $record) => $set($nama, CaraMenjawab::kunciDenganSumber(
                $get($kolomTipe) ?: $record?->{$kolomTipe},
                $get('format_isian') ?: $record?->format_isian,
                $get('referensi_master') ?: $record?->referensi_master,
                $get('opsi_pilihan') ?: $record?->opsi_pilihan,
            )))
            ->afterStateUpdated(function (Set $set, ?string $state) use ($kolomTipe): void {
                $cara = CaraMenjawab::dari($state);
                $set($kolomTipe, $cara['tipe'] ?? null);
                $set('format_isian', $cara['format'] ?? null);
                if ($state !== 'pilihan') {
                    $set('opsi_pilihan', null);
                }
                if ($state !== 'pilihan_data') {
                    $set('referensi_master', null);
                }
            })
            ->dehydrated(false)
            ->required(fn (Get $get): bool => blank($get($kolomTipe)))
            ->markAsRequired()
            ->live();
    }

    /**
     * Isian pilihan jawaban yang muncul sesuai "Cara menjawab": daftar yang ditulis admin atau data referensi Nagari.
     *
     * @return list<Select|TagsInput>
     */
    private static function isianSumberPilihan(string $cara, string $kolomTipe): array
    {
        return [
            Select::make('referensi_master')
                ->label('Data yang dipakai sebagai pilihan')
                ->options(MasterReferensiHelper::getSelectSourceOptions())
                ->required(fn (Get $get): bool => $get($cara) === 'pilihan_data')
                ->live()
                ->dehydratedWhenHidden()
                ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => $get($kolomTipe) === 'select' ? $state : null)
                ->visible(fn (Get $get): bool => $get($cara) === 'pilihan_data')
                ->columnSpanFull(),

            TagsInput::make('opsi_pilihan')
                ->label('Pilihan jawaban')
                ->placeholder('Ketik satu pilihan, lalu tekan Enter')
                ->live()
                ->dehydratedWhenHidden()
                ->dehydrateStateUsing(fn (?array $state, Get $get): ?array => $get($kolomTipe) === 'select' ? $state : null)
                ->visible(fn (Get $get): bool => $get($cara) === 'pilihan')
                ->columnSpanFull(),
        ];
    }

    /**
     * Petunjuk singkat langkah 3; ajakan mengganti tulisan penanda hanya muncul selama tulisan itu masih ada.
     */
    private static function petunjukIsiSurat(mixed $template): HtmlString
    {
        $konten = is_array($template) ? (collect($template)->first()['konten'] ?? null) : null;
        $konten = is_string($konten) ? json_decode($konten, true) : $konten;
        $masihAdaPenanda = is_array($konten) && str_contains(TemplatSurat::teks($konten), TemplatSurat::PENANDA_REDAKSI);

        $langkah = [
            'Jawaban warga dari langkah 2 sudah masuk ke surat dengan sendirinya.',
            $masihAdaPenanda
                ? 'Ganti tulisan <strong>'.e(TemplatSurat::PENANDA_REDAKSI).'</strong> dengan kalimat keterangan surat, seperti mengetik biasa.'
                : 'Ubah kalimat surat seperti mengetik biasa.',
            'Untuk menyebut data warga di tengah kalimat, ketik <strong>{{</strong> lalu pilih datanya dari daftar.',
            'Untuk mengubah isi kotak abu-abu (tabel atau bagian yang tampil bila…), klik ikon pensil di kotak itu.',
        ];

        return new HtmlString('<ol style="list-style: decimal; padding-left: 1.25rem;"><li>'.implode('</li><li>', $langkah).'</li></ol>');
    }

    private static function gantiJudulDiSurat(Get $get, Set $set, string $jalurTemplate, string $kode, ?string $lama, string $baru, ?string $tabel = null): void
    {
        if ($kode === '') {
            return;
        }

        $template = $get($jalurTemplate);
        foreach (is_array($template) ? $template : [] as $kunci => $item) {
            $konten = is_array($item) ? ($item['konten'] ?? null) : null;
            $konten = is_string($konten) ? json_decode($konten, true) : $konten;
            if (is_array($konten)) {
                $set("{$jalurTemplate}.{$kunci}.konten", PenyelarasIsiSurat::gantiJudul($konten, $kode, $lama, $baru, $tabel));
            }

            break;
        }
    }

    private static function selaraskanIsiSurat(Get $get, Set $set): void
    {
        $data = self::denganIsiSuratSelaras([
            'skemaFormFields' => $get('skemaFormFields'),
            'templateSurats' => $get('templateSurats'),
        ]);

        $set('skemaFormFields', $data['skemaFormFields']);
        $set('templateSurats', $data['templateSurats']);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make(static::getSteps())
                    ->skippable()
                    ->columnSpanFull(),
            ]);
    }

    private static function guideAction(string $name): Action
    {
        return Action::make($name)
            ->label('Panduan 5 langkah')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Panduan membuat jenis surat')
            ->modalContent(view('filament.jenis-surat.panduan-builder'))
            ->modalSubmitAction(false);
    }

    /** @return array{namaSurat: string, jumlahPertanyaan: int, jumlahBerkas: int, status: string, masalah: array<string>} */
    private static function reviewData(Get $get): array
    {
        $status = (string) ($get('status') ?: 'draft');
        $fields = $get('skemaFormFields');
        $documents = $get('syaratDokumens');
        $templates = $get('templateSurats');
        $state = [
            'nama_surat' => $get('nama_surat'),
            'kode_klasifikasi' => $get('kode_klasifikasi'),
            'kode_unit' => $get('kode_unit'),
            'mode_counter' => $get('mode_counter'),
            'pola_format_nomor' => $get('pola_format_nomor'),
            'reset_counter' => $get('reset_counter'),
            'padding_digit' => $get('padding_digit'),
            'skemaFormFields' => is_array($fields) ? $fields : [],
            'templateSurats' => is_array($templates) ? $templates : [],
            'syaratDokumens' => is_array($documents) ? $documents : [],
        ];

        return [
            'namaSurat' => (string) ($state['nama_surat'] ?: 'Surat baru'),
            'jumlahPertanyaan' => count($state['skemaFormFields']),
            'jumlahBerkas' => count($state['syaratDokumens']),
            'status' => $status,
            'masalah' => $status === 'aktif' ? app(KesiapanJenisSurat::class)->masalah($state) : [],
        ];
    }

    /**
     * @return array<Step>
     */
    public static function getSteps(): array
    {
        return [
            Step::make('1. Nama & nomor')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Section::make('Nama surat')
                        ->description('Anda dapat menyimpan rancangan kapan saja melalui langkah terakhir.')
                        ->headerActions([self::guideAction('panduanBuilderNama')])
                        ->schema([
                            TextInput::make('nama_surat')
                                ->label('Nama yang akan dilihat warga')
                                ->helperText('Gunakan nama resmi pada surat, misalnya Surat Keterangan Usaha.')
                                ->required()
                                ->maxLength(150)
                                ->unique(ignoreRecord: true)
                                ->live(onBlur: true),
                        ]),
                    Section::make('Nomor surat')
                        ->description('Kode awal sudah terisi. Sesuaikan dengan buku register Nagari bila diperlukan.')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('kode_klasifikasi')
                                    ->label('Kode klasifikasi')
                                    ->required()
                                    ->maxLength(50)
                                    ->default('400.10.2.2')
                                    ->helperText('Nilai awal 400.10.2.2 dapat diganti sesuai register jenis surat ini.')
                                    ->datalist(fn (): array => JenisSurat::query()->distinct()->orderBy('kode_klasifikasi')->pluck('kode_klasifikasi')->all())
                                    ->live(onBlur: true),
                                TextInput::make('kode_unit')
                                    ->label('Kode unit')
                                    ->required()
                                    ->maxLength(20)
                                    ->default('TUU')
                                    ->helperText('Nilai awal TUU dapat diganti sesuai unit yang berwenang menerbitkan surat ini.')
                                    ->datalist(fn (): array => JenisSurat::query()->distinct()->orderBy('kode_unit')->pluck('kode_unit')->all())
                                    ->live(onBlur: true),
                            ]),
                            ViewField::make('nomor_preview')
                                ->view('filament.jenis-surat.nomor-preview-helper')
                                ->columnSpanFull(),
                            Section::make('Pengaturan nomor lanjutan')
                                ->description('Nilai awal sudah sesuai untuk sebagian besar surat. Buka hanya bila buku register Nagari memakai aturan lain.')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    Select::make('mode_counter')
                                        ->label('Nomor urut dihitung untuk')
                                        ->helperText('Pilih siapa yang berbagi urutan angka. Umumnya tiap jenis surat mempunyai urutannya sendiri.')
                                        ->options([
                                            'per_jenis_surat' => 'Jenis surat ini saja',
                                            'per_klasifikasi' => 'Semua jenis dengan kode klasifikasi yang sama',
                                            'global' => 'Seluruh jenis surat',
                                        ])
                                        ->default('per_jenis_surat')
                                        ->required()
                                        ->live(),
                                    Select::make('preset_format')
                                        ->label('Susunan nomor surat')
                                        ->options([
                                            'standar' => 'Kode klasifikasi / nomor urut / kode unit / tahun',
                                            'kustom' => 'Saya atur susunan sendiri',
                                        ])
                                        ->default('standar')
                                        ->afterStateHydrated(function (Set $set, ?Model $record, ?string $state) {
                                            $pola = $record?->pola_format_nomor;
                                            $isStandar = blank($pola) || in_array($pola, [
                                                '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
                                                '[Kode Klasifikasi]/[Nomor Urut]/[Kode Unit]/[Tahun]',
                                            ]);
                                            $set('preset_format', $isStandar ? 'standar' : 'kustom');
                                            if (! $isStandar && filled($pola)) {
                                                $set('pola_kustom_tersimpan', $pola);
                                            }
                                        })
                                        ->dehydrated(false)
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                            if ($state === 'standar') {
                                                $currentPattern = $get('pola_format_nomor');
                                                if (filled($currentPattern) && $currentPattern !== NomorSuratFormatter::DEFAULT_PATTERN) {
                                                    $set('pola_kustom_tersimpan', $currentPattern);
                                                }
                                                $set('pola_format_nomor', NomorSuratFormatter::DEFAULT_PATTERN);
                                            } elseif ($state === 'kustom') {
                                                $set('pola_format_nomor', $get('pola_kustom_tersimpan') ?: NomorSuratFormatter::DEFAULT_PATTERN);
                                            }
                                        }),
                                    Hidden::make('pola_kustom_tersimpan')
                                        ->dehydrated(false),
                                    TextInput::make('pola_format_nomor')
                                        ->label('Susunan nomor buatan sendiri')
                                        ->extraInputAttributes(['id' => 'input_pola_format_nomor'])
                                        ->default('{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}')
                                        ->maxLength(150)
                                        ->required(fn (Get $get): bool => $get('preset_format') === 'kustom')
                                        ->visible(fn (Get $get): bool => $get('preset_format') === 'kustom')
                                        ->dehydrated(true)
                                        ->dehydrateStateUsing(function ($state, Get $get) {
                                            if ($get('preset_format') === 'standar') {
                                                return '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}';
                                            }

                                            return $state;
                                        })
                                        ->columnSpanFull()
                                        ->live(onBlur: true),
                                    ViewField::make('variabel_tags')
                                        ->view('filament.jenis-surat.variabel-nomor-helper')
                                        ->visible(fn (Get $get): bool => $get('preset_format') === 'kustom')
                                        ->columnSpanFull(),
                                    Grid::make(2)->schema([
                                        Select::make('padding_digit')
                                            ->label('Tampilan angka urut')
                                            ->options([
                                                3 => '001, 002, 003',
                                                0 => '1, 2, 3',
                                            ])
                                            ->default(3)
                                            ->required()
                                            ->live(),
                                        Select::make('reset_counter')
                                            ->label('Mulai ulang nomor')
                                            ->helperText('Jika memilih mulai ulang tiap tahun, susunan nomor harus memuat Tahun.')
                                            ->options([
                                                'tahunan' => 'Kembali ke 1 setiap awal tahun',
                                                'tidak_pernah' => 'Terus bertambah tanpa mulai ulang',
                                            ])
                                            ->default('tahunan')
                                            ->required()
                                            ->live(),
                                    ]),
                                ]),
                        ]),
                ]),

            Step::make('2. Form warga')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Section::make('Pertanyaan tambahan')
                        ->description('Nama, NIK, dan data dasar warga sudah diambil dari data penduduk. Tambahkan hanya pertanyaan khusus untuk surat ini.')
                        ->headerActions([self::guideAction('panduanBuilderForm')])
                        ->schema([
                            Repeater::make('skemaFormFields')
                                ->relationship('skemaFormFields')
                                ->label('Daftar pertanyaan')
                                ->hiddenLabel()
                                ->defaultItems(0)
                                ->orderColumn('urutan')
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::selaraskanIsiSurat($get, $set))
                                ->itemLabel(function (array $state): ?string {
                                    $label = $state['label'] ?? null;
                                    if (! $label) {
                                        return null;
                                    }

                                    $parts = array_filter([
                                        CaraMenjawab::ringkasan($state['tipe_field'] ?? null, $state['format_isian'] ?? null, $state['referensi_master'] ?? null),
                                        ! empty($state['wajib']) ? 'Wajib' : 'Boleh dilewati',
                                        filled($state['parent_group'] ?? null) ? 'Kelompok: '.ucwords(str_replace('_', ' ', $state['parent_group'])) : null,
                                    ]);

                                    return $label.' · '.implode(' · ', $parts);
                                })
                                ->addActionLabel('Tambah pertanyaan')
                                ->reorderable()
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('label')
                                            ->label('Pertanyaan yang dilihat warga')
                                            ->placeholder('Contoh: Nama usaha')
                                            ->required()
                                            ->maxLength(150)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?string $old): void {
                                                if (blank($get('nama_field')) && filled($state)) {
                                                    $set('nama_field', KodeIsian::buat($state, collect($get('../'))->pluck('nama_field')));
                                                } elseif (filled($state)) {
                                                    self::gantiJudulDiSurat($get, $set, '../../templateSurats', (string) $get('nama_field'), $old, $state);
                                                }
                                            }),

                                        self::isianCaraMenjawab('cara_menjawab', 'Cara menjawab', 'tipe_field', CaraMenjawab::pilihanPertanyaan())
                                            ->helperText(fn (Get $get): ?string => $get('tipe_field') === 'file'
                                                ? 'Berkas tambahan tersimpan privat sebagai lampiran pengajuan. Untuk dokumen persyaratan seperti KTP atau KK, atur di langkah 4.'
                                                : null),
                                    ]),

                                    Hidden::make('nama_field'),
                                    Hidden::make('tipe_field'),
                                    Hidden::make('format_isian'),

                                    ...self::isianSumberPilihan('cara_menjawab', 'tipe_field'),

                                    Toggle::make('wajib')
                                        ->label('Wajib dijawab')
                                        ->default(true)
                                        ->dehydrateStateUsing(fn (mixed $state): bool => $state === null ? true : (bool) $state),

                                    Section::make('Pengaturan lain')
                                        ->description('Buka bila pertanyaan hanya muncul pada keadaan tertentu, perlu dikelompokkan, atau tidak perlu dicetak.')
                                        ->collapsible()
                                        ->collapsed(fn (Get $get): bool => ($get('kondisi_tipe') ?: 'selalu') === 'selalu' && blank($get('parent_group')) && ! $get('hanya_pemeriksaan'))
                                        ->compact()
                                        ->schema([
                                            Grid::make(2)->schema([
                                                Select::make('kondisi_tipe')
                                                    ->label('Kapan pertanyaan muncul?')
                                                    ->options([
                                                        'selalu' => 'Selalu',
                                                        'pilihan' => 'Hanya jika warga memilih jawaban tertentu',
                                                    ])
                                                    ->default('selalu')
                                                    ->selectablePlaceholder(false)
                                                    ->dehydrateStateUsing(fn (?string $state): string => $state ?: 'selalu')
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set): void {
                                                        $set('kondisi_kunci', null);
                                                        $set('kondisi_nilai', null);
                                                    }),
                                                Select::make('pengelompokan_ui')
                                                    ->label('Kelompokkan dengan pertanyaan lain?')
                                                    ->options([
                                                        'sendiri' => 'Tidak',
                                                        'kelompok' => 'Ya, tampil bersama dalam satu kelompok',
                                                        'pilihan_warga' => 'Ya, dan warga boleh memilih tidak mengisi kelompok ini',
                                                    ])
                                                    ->default('sendiri')
                                                    ->selectablePlaceholder(false)
                                                    ->dehydrated(false)
                                                    ->live()
                                                    ->afterStateHydrated(function (Set $set, Get $get) {
                                                        $set('pengelompokan_ui', blank($get('parent_group'))
                                                            ? 'sendiri'
                                                            : ($get('is_optional_group') ? 'pilihan_warga' : 'kelompok'));
                                                    })
                                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                                        if ($state === 'sendiri') {
                                                            $set('parent_group', null);
                                                        }
                                                        $set('is_optional_group', $state === 'pilihan_warga');
                                                    }),
                                                Select::make('kondisi_kunci')
                                                    ->label('Bergantung pada pertanyaan')
                                                    ->options(fn (Get $get, ?Model $record = null): array => self::fieldConditionKeys($get, $record))
                                                    ->required(fn (Get $get): bool => $get('kondisi_tipe') === 'pilihan')
                                                    ->visible(fn (Get $get): bool => $get('kondisi_tipe') === 'pilihan')
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('kondisi_nilai', null)),
                                                Select::make('kondisi_nilai')
                                                    ->label('Jika jawabannya')
                                                    ->options(fn (Get $get, ?Model $record = null): array => self::conditionValues($get, $record))
                                                    ->required(fn (Get $get): bool => $get('kondisi_tipe') === 'pilihan')
                                                    ->visible(fn (Get $get): bool => $get('kondisi_tipe') === 'pilihan')
                                                    ->live()
                                                    ->searchable(),
                                                TextInput::make('parent_group')
                                                    ->label('Nama kelompok')
                                                    ->helperText('Pertanyaan dengan nama kelompok yang sama tampil bersama. Contoh: Data Saksi.')
                                                    ->maxLength(50)
                                                    ->datalist(fn (Get $get, ?Model $record = null): array => static::getParentGroupSuggestions($get, $record))
                                                    ->required(fn (Get $get): bool => in_array($get('pengelompokan_ui'), ['kelompok', 'pilihan_warga'], true))
                                                    ->visible(fn (Get $get): bool => filled($get('parent_group')) || in_array($get('pengelompokan_ui'), ['kelompok', 'pilihan_warga'], true))
                                                    ->afterStateHydrated(function (Set $set, ?string $state) {
                                                        if (filled($state) && Str::contains($state, '_')) {
                                                            $set('parent_group', ucwords(str_replace('_', ' ', $state)));
                                                        }
                                                    })
                                                    ->live(onBlur: true),
                                                Hidden::make('is_optional_group')
                                                    ->default(false)
                                                    ->dehydrated(),
                                            ]),

                                            Toggle::make('hanya_pemeriksaan')
                                                ->label('Jangan cetak jawaban ini di surat (hanya dilihat petugas)')
                                                ->default(false)
                                                ->live()
                                                ->visible(fn (Get $get): bool => $get('tipe_field') !== 'file'),
                                        ]),

                                    Section::make('Kolom yang diisi pada setiap baris')
                                        ->visible(fn (Get $get): bool => $get('tipe_field') === 'table_repeater')
                                        ->schema([
                                            Repeater::make('kolomTabels')
                                                ->relationship('kolomTabels')
                                                ->label('Kolom')
                                                ->hiddenLabel()
                                                ->defaultItems(0)
                                                ->orderColumn('urutan')
                                                ->itemLabel(function (array $state): ?string {
                                                    $label = $state['label'] ?? null;

                                                    return $label ? $label.' · '.CaraMenjawab::ringkasan($state['tipe_kolom'] ?? null, $state['format_isian'] ?? null, $state['referensi_master'] ?? null) : null;
                                                })
                                                ->schema([
                                                    Grid::make(2)->schema([
                                                        TextInput::make('label')
                                                            ->label('Judul kolom')
                                                            ->required()
                                                            ->maxLength(150)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?string $old): void {
                                                                if (blank($get('nama_kolom')) && filled($state)) {
                                                                    $set('nama_kolom', KodeIsian::buat($state, collect($get('../'))->pluck('nama_kolom'), 'kolom'));
                                                                } elseif (filled($state)) {
                                                                    self::gantiJudulDiSurat($get, $set, '../../../../templateSurats', (string) $get('nama_kolom'), $old, $state, (string) $get('../../nama_field'));
                                                                }
                                                            }),

                                                        self::isianCaraMenjawab('cara_mengisi', 'Cara mengisi kolom', 'tipe_kolom', CaraMenjawab::pilihanKolom()),
                                                    ]),

                                                    Hidden::make('nama_kolom'),
                                                    Hidden::make('tipe_kolom'),
                                                    Hidden::make('format_isian'),

                                                    ...self::isianSumberPilihan('cara_mengisi', 'tipe_kolom'),

                                                    Toggle::make('wajib')
                                                        ->label('Wajib diisi')
                                                        ->default(true)
                                                        ->dehydrateStateUsing(fn (mixed $state): bool => $state === null ? true : (bool) $state),
                                                ])
                                                ->addActionLabel('Tambah kolom')
                                                ->reorderable(),
                                        ]),
                                ]),
                        ]),
                ]),

            Step::make('3. Isi surat')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Section::make('Isi surat resmi')
                        ->headerActions([
                            self::guideAction('panduanBuilderIsi'),
                            Action::make('simulasiPdf')
                                ->label('Lihat contoh PDF tersimpan')
                                ->icon('heroicon-o-eye')
                                ->color('warning')
                                ->tooltip('Simpan perubahan builder dahulu; PDF memakai data yang sudah tersimpan.')
                                ->url(fn (?Model $record): ?string => $record ? route('jenis-surat.simulasi-pdf', ['jenisSurat' => $record->id]) : null)
                                ->visible(fn (?Model $record): bool => filled($record?->id))
                                ->openUrlInNewTab(),
                        ])
                        ->schema([
                            Callout::make('Cara mengubah isi surat')
                                ->info()
                                ->description(fn (Get $get): HtmlString => self::petunjukIsiSurat($get('templateSurats')))
                                ->columnSpanFull(),

                            // Bingkai Kop Surat Visual
                            ViewField::make('bingkai_kop_surat')
                                ->view('filament.jenis-surat.bingkai-kop-surat')
                                ->viewData(fn (Get $get, ?Model $record = null) => [
                                    'namaSurat' => $get('nama_surat') ?: ($record?->nama_surat ?? 'SURAT KETERANGAN'),
                                    'kodeKlasifikasi' => $get('kode_klasifikasi') ?: $record?->kode_klasifikasi,
                                    'kodeUnit' => $get('kode_unit') ?: $record?->kode_unit,
                                    'polaNomor' => $get('pola_format_nomor') ?: ($record?->pola_format_nomor ?? '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}'),
                                    'paddingDigit' => $get('padding_digit') ?? ($record?->padding_digit ?? 3),
                                    'resetCounter' => $get('reset_counter') ?? ($record?->reset_counter ?? 'tahunan'),
                                ])
                                ->columnSpanFull(),

                            Repeater::make('templateSurats')
                                ->relationship('templateSurats')
                                ->maxItems(1)
                                ->defaultItems(1)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->hiddenLabel()
                                ->schema([
                                    RichEditor::make('konten')
                                        ->hiddenLabel()
                                        ->required()
                                        ->json()
                                        ->default(fn (): array => TemplatSurat::bawaan())
                                        ->mergeTags(fn (Get $get): array => app(KatalogTagSurat::class)->daftar((array) ($get('../../skemaFormFields') ?? [])))
                                        ->customBlocks(PenyusunSurat::BLOK)
                                        ->toolbarButtons([
                                            ['bold', 'italic', 'underline'],
                                            ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                                            ['bulletList', 'orderedList'],
                                            ['mergeTags', 'customBlocks'],
                                            ['undo', 'redo'],
                                        ])
                                        ->extraInputAttributes([
                                            'class' => 'font-serif text-[13.5px] leading-relaxed',
                                            'style' => 'font-family: "Bookman Old Style", "Times New Roman", Georgia, serif; min-height: 480px; background-color: #ffffff;',
                                        ])
                                        ->extraAttributes([
                                            'class' => 'surat-template-editor border-x border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900',
                                        ])
                                        ->columnSpanFull(),

                                    Hidden::make('status_aktif')
                                        ->default(true),
                                ])
                                ->columnSpanFull(),

                            ViewField::make('bingkai_ttd_surat')
                                ->view('filament.jenis-surat.bingkai-ttd-surat')
                                ->columnSpanFull(),

                        ]),
                ]),

            Step::make('4. Berkas')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    Section::make('Berkas persyaratan')
                        ->description('Berkas di sini akan diminta saat pengajuan. Bila surat ini tidak memerlukan unggahan, biarkan daftar kosong.')
                        ->headerActions([self::guideAction('panduanBuilderBerkas')])
                        ->schema([
                            Repeater::make('syaratDokumens')
                                ->relationship('syaratDokumens')
                                ->hiddenLabel()
                                ->defaultItems(0)
                                ->orderColumn('urutan')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('nama_dokumen')
                                            ->label('Nama berkas yang diminta')
                                            ->helperText('Contoh: foto KTP, kartu keluarga, atau surat keterangan pendukung.')
                                            ->required()
                                            ->columnSpanFull()
                                            ->datalist(fn () => MasterSyaratDokumen::orderBy('nama_dokumen')->pluck('nama_dokumen')->toArray())
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                                if (filled($state) && blank($get('keterangan'))) {
                                                    $master = MasterSyaratDokumen::where('nama_dokumen', trim($state))->first();
                                                    if ($master && filled($master->keterangan_default)) {
                                                        $set('keterangan', $master->keterangan_default);
                                                    }
                                                }
                                            }),
                                        TextInput::make('keterangan')
                                            ->label('Petunjuk untuk warga')
                                            ->helperText('Jelaskan berkas yang diterima bila namanya belum cukup jelas.')
                                            ->columnSpanFull(),
                                        Toggle::make('wajib')
                                            ->label('Wajib diunggah')
                                            ->default(true)
                                            ->dehydrateStateUsing(fn (mixed $state): bool => $state === null ? true : (bool) $state),
                                        Select::make('kondisi_tipe')
                                            ->label('Kapan berkas ini diminta?')
                                            ->options([
                                                'selalu' => 'Pada setiap pengajuan',
                                                'kelompok' => 'Jika warga mengisi kelompok pertanyaan tertentu',
                                                'pilihan' => 'Jika warga memilih jawaban tertentu',
                                            ])
                                            ->default('selalu')
                                            ->dehydrateStateUsing(fn (?string $state): string => $state ?: 'selalu')
                                            ->live()
                                            ->afterStateUpdated(function (Set $set): void {
                                                $set('kondisi_kunci', null);
                                                $set('kondisi_nilai', null);
                                            }),
                                        Select::make('kondisi_kunci')
                                            ->label(fn (Get $get): string => $get('kondisi_tipe') === 'kelompok' ? 'Kelompok pertanyaan' : 'Pertanyaan pilihan')
                                            ->options(fn (Get $get, ?Model $record = null): array => self::conditionKeys($get, $record))
                                            ->required(fn (Get $get): bool => in_array($get('kondisi_tipe'), ['kelompok', 'pilihan'], true))
                                            ->visible(fn (Get $get): bool => in_array($get('kondisi_tipe'), ['kelompok', 'pilihan'], true))
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set) => $set('kondisi_nilai', null)),
                                        Select::make('kondisi_nilai')
                                            ->label('Jawaban yang membuat berkas diminta')
                                            ->options(fn (Get $get, ?Model $record = null): array => self::conditionValues($get, $record))
                                            ->required(fn (Get $get): bool => $get('kondisi_tipe') === 'pilihan')
                                            ->visible(fn (Get $get): bool => $get('kondisi_tipe') === 'pilihan')
                                            ->live()
                                            ->searchable(),
                                    ]),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['nama_dokumen'] ?? null)
                                ->addActionLabel('Tambah berkas persyaratan')
                                ->reorderable(),
                        ]),
                ]),
            Step::make('5. Aktifkan')
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Section::make('Ketersediaan layanan')
                        ->headerActions([self::guideAction('panduanBuilderAktifkan')])
                        ->schema([
                            Select::make('status')
                                ->label('Siapa yang dapat mengajukan surat ini?')
                                ->helperText('Mengaktifkan jenis surat membuka pengajuan. Surat resmi tetap diterbitkan oleh Wali Nagari setelah verifikasi.')
                                ->options([
                                    'draft' => 'Belum dibuka; simpan sebagai rancangan',
                                    'aktif' => 'Warga dapat mengajukan',
                                    'nonaktif' => 'Tutup pengajuan baru',
                                ])
                                ->default('draft')
                                ->required()
                                ->live(),
                        ]),
                    ViewField::make('ringkasan_pengaturan')
                        ->view('filament.jenis-surat.ringkasan-pengaturan')
                        ->viewData(fn (Get $get): array => self::reviewData($get))
                        ->columnSpanFull(),
                ]),
        ];
    }
}
