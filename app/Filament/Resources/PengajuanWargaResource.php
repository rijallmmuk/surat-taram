<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Nagaris\NagariResource;
use App\Filament\Resources\Penduduks\PendudukResource;
use App\Filament\Resources\PengajuanWalkInResource\Pages\CreatePengajuanWalkIn;
use App\Filament\Resources\PengajuanWargaResource\Pages\CreatePengajuanWarga;
use App\Filament\Resources\PengajuanWargaResource\Pages\ListPengajuanWargas;
use App\Filament\Resources\PengajuanWargaResource\Pages\ViewPengajuanWarga;
use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Models\JenisSurat;
use App\Models\Nagari;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\PermintaanPerubahanData;
use App\Models\SkemaFormField;
use App\Models\User;
use App\Services\CaraMenjawab;
use App\Services\DokumenWargaService;
use App\Services\KelengkapanDataPemohon;
use App\Services\KesiapanPenandatanganan;
use App\Services\KondisiFormEvaluator;
use App\Services\MasterReferensiHelper;
use App\Services\NomorSuratGenerator;
use App\Services\PdfSuratGenerator;
use App\Services\PendudukOptionService;
use App\Services\PengajuanWargaReview;
use App\Services\SyaratDokumenApplicability;
use App\Support\Dashboard\TaskCounts;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class PengajuanWargaResource extends Resource
{
    protected static ?string $model = PengajuanSurat::class;

    protected static ?string $navigationLabel = 'Pengajuan Surat';

    protected static ?string $modelLabel = 'Pengajuan Surat';

    protected static ?string $pluralModelLabel = 'Pengajuan Surat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Mandiri Warga';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        return $user?->role === 'warga' ? app(TaskCounts::class)->badge($user, 'pengajuan_warga') : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pengajuan surat yang sedang diproses';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->role === 'warga';
    }

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        // Jalur mandiri warga juga dapat diakses pengelola.
        return in_array($user?->role, ['warga', 'superadmin', 'admin'], true);
    }

    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user?->isAdministrator()) {
            return true;
        }

        return $user?->role === 'warga' && $user->penduduk !== null;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        $query = parent::getEloquentQuery()->with('jenisSurat');

        if ($user->role === 'warga') {
            return $query
                ->milik($user)
                ->latest('created_at');
        }

        return $query->latest('created_at');
    }

    public static function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = auth()->user();
        $penduduk = $user?->penduduk;
        $permintaanMenunggu = $user?->role === 'warga'
            ? PermintaanPerubahanData::query()->where('diajukan_oleh_user_id', $user->id)->where('status', 'menunggu')->first()
            : null;
        $dataBelumTerisi = $user?->role === 'warga' && $penduduk
            ? app(KelengkapanDataPemohon::class)->yangBelumTerisi($penduduk)
            : [];
        $isWalkIn = $schema->getLivewire() instanceof CreatePengajuanWalkIn;
        $cancelUrl = $isWalkIn ? PengajuanWalkInResource::getUrl() : static::getUrl();
        $submitLabel = $user?->role === 'warga' ? 'Kirim pengajuan surat' : 'Kirim pengajuan warga';

        return $schema->components([
            Wizard::make([
                Step::make($user?->role === 'warga' ? 'Periksa data diri' : 'Pilih warga pemohon')
                    ->description('Langkah 1 dari 4')
                    ->icon('heroicon-o-identification')
                    ->afterValidation(function (Get $get, CreateRecord $livewire) use ($user): void {
                        $pemohon = self::pemohonTerpilih($user, $get);
                        if (! $pemohon) {
                            throw ValidationException::withMessages(['data.penduduk_nik' => 'Pilih warga pemohon yang terdaftar.']);
                        }

                        $belumTerisi = app(KelengkapanDataPemohon::class)->yangBelumTerisi($pemohon);
                        if ($belumTerisi !== []) {
                            if ($user?->role === 'warga') {
                                $permintaanMenunggu = PermintaanPerubahanData::query()
                                    ->where('diajukan_oleh_user_id', $user->id)
                                    ->where('status', 'menunggu')
                                    ->first();

                                $livewire->redirect($permintaanMenunggu
                                    ? PermintaanPerubahanDataResource::getUrl('view', ['record' => $permintaanMenunggu])
                                    : PermintaanPerubahanDataResource::getUrl('create', ['mode' => 'lengkapi']));

                                throw new Halt;
                            }

                            throw ValidationException::withMessages([
                                'data_pemohon' => 'Lengkapi data pemohon sebelum melanjutkan: '.implode(', ', $belumTerisi).'.',
                            ]);
                        }
                    })
                    ->schema([
                        Section::make($user?->role !== 'warga' ? 'Data warga pemohon' : 'Data resmi Anda')
                            ->description($user?->role !== 'warga'
                                ? 'Pilih warga dengan data lengkap sebelum melanjutkan pengajuan.'
                                : 'Data berikut akan dipakai pada surat. Periksa nama, NIK, dan keterangan lainnya sebelum melanjutkan.')
                            ->schema(function () use ($penduduk, $user): array {
                                if ($user?->role !== 'warga') {
                                    return [
                                        Select::make('penduduk_nik')
                                            ->label('Pilih warga pemohon')
                                            ->searchable()
                                            ->getSearchResultsUsing(fn (string $search): array => app(PendudukOptionService::class)->search($search))
                                            ->getOptionLabelUsing(fn (?string $value): ?string => app(PendudukOptionService::class)->label($value))
                                            ->searchPrompt('Ketik NIK atau nama warga')
                                            ->required()
                                            ->live()
                                            ->columnSpanFull(),
                                        View::make('filament.penduduk.data-diri')
                                            ->viewData(fn (Get $get): array => [
                                                'penduduk' => self::pemohonTerpilih($user, $get)?->loadMissing([
                                                    'jorong', 'agama', 'statusKawin', 'pekerjaan', 'pendidikan', 'kewarganegaraan',
                                                ]),
                                            ])
                                            ->visible(fn (Get $get): bool => self::pemohonTerpilih($user, $get) !== null)
                                            ->columnSpanFull(),
                                    ];
                                }

                                if (! $penduduk) {
                                    return [
                                        Placeholder::make('peringatan_akun_tidak_valid')
                                            ->label('Peringatan Akses')
                                            ->content('Akun Anda tidak terhubung dengan data kependudukan aktif di Nagari Taram. Anda tidak dapat mengajukan permohonan surat. Silakan hubungi kantor nagari.')
                                            ->columnSpanFull(),
                                    ];
                                }

                                return [
                                    View::make('filament.penduduk.data-diri')
                                        ->viewData(['penduduk' => $penduduk->loadMissing([
                                            'jorong', 'agama', 'statusKawin', 'pekerjaan', 'pendidikan', 'kewarganegaraan',
                                        ])])
                                        ->columnSpanFull(),
                                ];
                            })
                            ->columns(1),
                        Section::make('Data warga perlu dilengkapi')
                            ->description('Lengkapi data warga yang dipilih sebelum melanjutkan pengajuan.')
                            ->visible(fn (Get $get): bool => $user?->role !== 'warga'
                                && ($pemohon = self::pemohonTerpilih($user, $get)) !== null
                                && app(KelengkapanDataPemohon::class)->yangBelumTerisi($pemohon) !== [])
                            ->schema([
                                Placeholder::make('daftar_data_belum_lengkap')
                                    ->label('Data yang perlu dilengkapi')
                                    ->content(function (Get $get) use ($user): string {
                                        $pemohon = self::pemohonTerpilih($user, $get);

                                        return $pemohon
                                            ? implode(', ', app(KelengkapanDataPemohon::class)->yangBelumTerisi($pemohon))
                                            : '';
                                    }),
                                Placeholder::make('tautan_lengkapi_data_warga')
                                    ->hiddenLabel()
                                    ->content(function (Get $get) use ($user): HtmlString {
                                        $pemohon = self::pemohonTerpilih($user, $get);

                                        return new HtmlString($pemohon
                                            ? '<a class="taram-koreksi-link" href="'.e(PendudukResource::getUrl('edit', ['record' => $pemohon])).'">Lengkapi data</a>'
                                            : '');
                                    }),
                            ]),
                        Section::make($dataBelumTerisi !== [] ? 'Data diri perlu dilengkapi' : 'Ada data yang perlu diubah?')
                            ->description($permintaanMenunggu
                                ? 'Permintaan Anda sedang diperiksa petugas. Data resmi berubah setelah disetujui.'
                                : ($dataBelumTerisi !== []
                                    ? 'Data berikut wajib dilengkapi. Gunakan tombol Lengkapi data di bawah untuk mengirim usulan kepada petugas Nagari.'
                                    : 'Jika ada data yang keliru atau berubah, ajukan perbaikan untuk diperiksa petugas Nagari.'))
                            ->visible(fn (): bool => auth()->user()?->role === 'warga')
                            ->schema([
                                Placeholder::make('data_wajib_belum_terisi')
                                    ->label('Data yang perlu dilengkapi')
                                    ->content(implode(', ', $dataBelumTerisi))
                                    ->visible($dataBelumTerisi !== []),
                                Placeholder::make('tautan_perubahan_data')
                                    ->hiddenLabel()
                                    ->visible(! $permintaanMenunggu || $dataBelumTerisi === [])
                                    ->content(new HtmlString($dataBelumTerisi !== []
                                        ? '<p class="taram-koreksi-note">Data yang sudah terisi keliru? <a href="'.e(PermintaanPerubahanDataResource::getUrl('create', ['mode' => 'koreksi'])).'">Ajukan perubahan data</a></p>'
                                        : '<a class="taram-koreksi-link" href="'.e($permintaanMenunggu
                                            ? PermintaanPerubahanDataResource::getUrl('view', ['record' => $permintaanMenunggu])
                                            : PermintaanPerubahanDataResource::getUrl('create', ['mode' => 'koreksi'])).'">'.($permintaanMenunggu ? 'Lihat permintaan Anda' : 'Ajukan perubahan data').'</a>')),
                            ]),
                    ]),

                Step::make('Pilih surat')
                    ->description('Langkah 2 dari 4')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Section::make('Pilihan Surat')
                            ->description('Pilih satu layanan yang sesuai dengan keperluan Anda.')
                            ->schema([
                                Placeholder::make('surat_tidak_tersedia')
                                    ->hiddenLabel()
                                    ->content('Belum ada jenis surat yang dibuka untuk pengajuan. Silakan kembali lagi nanti atau hubungi kantor nagari.')
                                    ->visible(fn (): bool => ! JenisSurat::where('status', 'aktif')->exists()),
                                Radio::make('jenis_surat_id')
                                    ->label('Daftar surat yang dapat diajukan')
                                    ->options(fn () => JenisSurat::where('status', 'aktif')->orderBy('id')->pluck('nama_surat', 'id'))
                                    ->columns(['default' => 1, 'md' => 2])
                                    ->extraAttributes(['class' => 'taram-pilihan-surat'])
                                    ->disableOptionWhen(fn (string $value, Get $get): bool => in_array((int) $value, self::jenisSuratSedangDiproses($get), true))
                                    ->descriptions(fn (Get $get): array => array_fill_keys(
                                        self::jenisSuratSedangDiproses($get),
                                        'Masih ada pengajuan yang sedang diproses.',
                                    ))
                                    ->required()
                                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        if (in_array((int) $value, self::jenisSuratSedangDiproses($get), true)) {
                                            $fail('Masih ada pengajuan surat ini atas nama pemohon yang sedang diproses. Tunggu hingga selesai atau batalkan pengajuan tersebut.');
                                        }
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        $dataIsian = [];
                                        $berkasSyarat = [];
                                        if ($state) {
                                            $jenisSurat = JenisSurat::with(['skemaFormFields', 'syaratDokumens'])->find($state);
                                            if ($jenisSurat) {
                                                foreach ($jenisSurat->skemaFormFields as $field) {
                                                    $dataIsian[$field->nama_field] = null;
                                                    if ($field->is_optional_group && $field->parent_group) {
                                                        $dataIsian['sertakan_'.$field->parent_group] = false;
                                                    }
                                                }
                                                foreach ($jenisSurat->syaratDokumens as $syarat) {
                                                    $berkasSyarat[$syarat->id] = null;
                                                }
                                            }
                                        }
                                        $set('data_isian', $dataIsian);
                                        $set('berkas_syarat', $berkasSyarat);
                                        $set('nomor_surat_usulan', null);
                                        $set('nomor_urut_usulan', null);
                                    }),
                            ]),
                    ]),

                Step::make('Isi formulir')
                    ->description('Langkah 3 dari 4')
                    ->icon('heroicon-o-pencil-square')
                    ->afterValidation(fn (Get $get, Set $set) => self::isiUsulanNomor($get, $set))
                    ->schema([
                        Section::make('Keterangan tambahan')
                            ->description('Isi pertanyaan yang muncul sesuai keperluan surat. Tanda * menunjukkan isian wajib.')
                            ->visible(fn (Get $get): bool => filled($get('jenis_surat_id')))
                            ->schema(function (Get $get): array {
                                $jenisSuratId = $get('jenis_surat_id');
                                if (! $jenisSuratId) {
                                    return [];
                                }

                                $jenisSurat = JenisSurat::with(['skemaFormFields.kolomTabels'])->find($jenisSuratId);
                                if (! $jenisSurat || $jenisSurat->skemaFormFields->isEmpty()) {
                                    return [
                                        Placeholder::make('no_special_fields')
                                            ->label('Keterangan')
                                            ->content('Surat ini tidak memerlukan keterangan tambahan. Lanjutkan ke berkas dan tinjauan.'),
                                    ];
                                }

                                $components = [];
                                $ungrouped = $jenisSurat->skemaFormFields->filter(fn ($f) => empty($f->parent_group));
                                $grouped = $jenisSurat->skemaFormFields->filter(fn ($f) => ! empty($f->parent_group))->groupBy('parent_group');

                                foreach ($ungrouped as $field) {
                                    $components[] = self::buildFormFieldComponent($field);
                                }

                                foreach ($grouped as $groupKey => $groupFields) {
                                    $isOptional = $groupFields->contains(fn ($field): bool => (bool) $field->is_optional_group);
                                    $groupTitle = match ($groupKey) {
                                        'data_ayah' => 'Data Ayah Kandung',
                                        'data_ibu' => 'Data Ibu Kandung',
                                        default => ucwords(str_replace('_', ' ', $groupKey)),
                                    };
                                    $toggleKey = 'data_isian.sertakan_'.$groupKey;

                                    $subFields = [];
                                    foreach ($groupFields as $f) {
                                        $subFields[] = self::buildFormFieldComponent($f, $isOptional);
                                    }

                                    if ($isOptional) {
                                        $components[] = Section::make($groupTitle)
                                            ->schema(function (Get $get) use ($groupTitle, $toggleKey, $subFields): array {
                                                $toggle = Toggle::make($toggleKey)
                                                    ->label('Sertakan '.$groupTitle)
                                                    ->live();

                                                if (! (bool) $get($toggleKey)) {
                                                    return [$toggle];
                                                }

                                                return [
                                                    $toggle,
                                                    Grid::make(['default' => 1, 'md' => 2])->schema($subFields),
                                                ];
                                            });
                                    } else {
                                        $components[] = Section::make($groupTitle)
                                            ->schema([
                                                Grid::make(['default' => 1, 'md' => 2])->schema($subFields),
                                            ]);
                                    }
                                }

                                return $components;
                            }),
                    ]),

                Step::make('Berkas dan tinjauan')
                    ->description('Langkah 4 dari 4')
                    ->icon('heroicon-o-paper-clip')
                    ->schema([
                        Section::make('Berkas persyaratan')
                            ->description(fn (): string => self::berkasWajib()
                                ? 'Berkas yang sudah tersimpan di sistem akan dipakai otomatis. Unggah berkas baru jika belum tersedia atau perlu diperbarui.'
                                : 'Dokumen fisik pemohon diperiksa langsung oleh petugas, sehingga unggahan berkas tidak wajib.')
                            ->visible(fn (Get $get): bool => filled($get('jenis_surat_id')))
                            ->schema(function (Get $get): array {
                                $jenisSuratId = $get('jenis_surat_id');
                                if (! $jenisSuratId) {
                                    return [];
                                }

                                $jenisSurat = JenisSurat::with('syaratDokumens')->find($jenisSuratId);
                                $syaratBerlaku = $jenisSurat?->syaratDokumens
                                    ->filter(fn ($syarat): bool => app(SyaratDokumenApplicability::class)->berlaku($syarat, (array) $get('data_isian')));

                                if (! $jenisSurat) {
                                    return [];
                                }

                                /** @var User $user */
                                $user = auth()->user();
                                $nik = $user?->role === 'warga'
                                    ? $user->penduduk?->nik
                                    : $get('penduduk_nik');
                                $dokumenWargaService = app(DokumenWargaService::class);

                                $uploads = [];
                                if ($syaratBerlaku->isEmpty()) {
                                    $uploads[] = Placeholder::make('no_syarat')
                                        ->label('Keterangan')
                                        ->content('Tidak ada berkas yang perlu diunggah untuk pilihan dan jawaban ini.');
                                }

                                foreach ($jenisSurat->syaratDokumens as $syarat) {
                                    $existing = $nik ? $dokumenWargaService->findDokumenWarga($nik, $syarat) : null;
                                    $isWajib = (bool) $syarat->wajib && ! $existing && self::berkasWajib();

                                    $label = $syarat->nama_dokumen.($existing ? ' (Sudah Tersimpan di Sistem)' : ($isWajib ? ' (Wajib)' : ' (Opsional)'));

                                    $helperHtml = $existing
                                        ? new HtmlString("<span class='text-emerald-600 font-medium'>✓ Berkas".(filled($existing->file_name) && $existing->file_name !== basename($existing->file_path) ? " '".e($existing->file_name)."'" : '').' sudah tersimpan di sistem'.($existing->uploaded_at ? ' (diunggah '.e($existing->uploaded_at->translatedFormat('d F Y')).')' : '').".</span> <a href='".e(route('dokumen.warga', $existing->id))."' target='_blank' class='text-primary-600 underline text-xs font-semibold ml-1'>Lihat Berkas ↗</a><br><span class='text-xs text-gray-500'>Unggah berkas baru jika dokumen perlu diperbarui.</span>")
                                        : (($syarat->keterangan ? $syarat->keterangan.' • ' : '').'Format: PDF, JPG, PNG (Maksimal 5 MB). Pastikan dokumen terbaca jelas.');

                                    $uploads[] = FileUpload::make("berkas_syarat.{$syarat->id}")
                                        ->label($label)
                                        ->validationAttribute($syarat->nama_dokumen)
                                        ->helperText($helperHtml)
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                        ->validationMessages([
                                            'mimetypes' => 'Berkas harus berupa PDF, JPG, atau PNG.',
                                            'max' => 'Ukuran berkas maksimal 5 MB.',
                                        ])
                                        ->maxSize(5120)
                                        ->storeFileNamesIn("nama_berkas_syarat.{$syarat->id}")
                                        ->directory('lampiran-pengajuan/'.date('Y'))
                                        ->disk('local')
                                        ->visibility('private')
                                        ->visible(fn (Get $get): bool => app(SyaratDokumenApplicability::class)->berlaku($syarat, (array) $get('data_isian')))
                                        ->required(fn (Get $get): bool => $isWajib && app(SyaratDokumenApplicability::class)->berlaku($syarat, (array) $get('data_isian')));
                                }

                                return $uploads;
                            }),
                        self::bagianNomorSurat(),
                        ViewField::make('ringkasan_pengajuan')
                            ->view('filament.pengajuan-review')
                            ->viewData(fn (Get $get): array => [
                                ...app(PengajuanWargaReview::class)->summarize(
                                    (int) $get('jenis_surat_id'),
                                    (array) $get('data_isian'),
                                    (array) $get('berkas_syarat'),
                                    $user?->role === 'warga'
                                        ? $user->penduduk?->nik
                                        : $get('penduduk_nik'),
                                ),
                                'isPetugas' => $user?->role !== 'warga',
                                'langsungDiverifikasi' => app(KesiapanPenandatanganan::class)->kendalaVerifikasi() === [],
                            ])
                            ->columnSpanFull(),
                    ]),
            ])
                ->nextAction(fn (Action $action): Action => $action
                    ->label($user?->role === 'warga' && $dataBelumTerisi !== []
                        ? ($permintaanMenunggu ? 'Lihat status permintaan' : 'Lengkapi data')
                        : 'Selanjutnya')
                    ->icon('heroicon-o-arrow-right'))
                ->previousAction(fn (Action $action): Action => $action->label('Kembali'))
                ->cancelAction(new HtmlString('<a class="taram-wizard-cancel" href="'.e($cancelUrl).'">Batal</a>'))
                ->submitAction(new HtmlString('<button type="submit" class="taram-wizard-submit" wire:loading.attr="disabled" wire:target="create"><span wire:loading.remove wire:target="create">'.e($submitLabel).'</span><span wire:loading wire:target="create">Mengirim pengajuan...</span></button>'))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('ringkasan_ponsel')
                    ->label('Pengajuan')
                    ->state(fn (PengajuanSurat $record): string => $record->jenisSurat?->nama_surat ?? 'Jenis surat tidak tersedia')
                    ->description(fn (PengajuanSurat $record): string => ($record->created_at?->translatedFormat('d M Y') ?? 'Tanggal tidak tersedia').' · '.self::labelStatusPengajuan($record->status))
                    ->weight('bold')
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('created_at')
                    ->label('Waktu Diajukan')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('jenisSurat.nama_surat')
                    ->label('Jenis Surat')
                    ->weight('bold')
                    ->wrap()
                    ->visibleFrom('md')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status Terkini')
                    ->formatStateUsing(fn (string $state): string => self::labelStatusPengajuan($state))
                    ->visibleFrom('md'),
                TextColumn::make('nomor_surat_final')
                    ->label('Nomor Surat Resmi')
                    ->placeholder('-')
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'diajukan' => 'Menunggu Verifikasi',
                        'diverifikasi' => 'Menunggu Tanda Tangan',
                        'diterbitkan' => 'Selesai Diterbitkan',
                        'ditolak' => 'Ditolak',
                        'dibatalkan' => 'Dibatalkan',
                    ]),
            ])
            ->recordActions([
                Action::make('downloadPdf')
                    ->label('Unduh surat')
                    ->iconButton()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Unduh PDF Resmi')
                    ->authorize(fn (PengajuanSurat $r): bool => (bool) auth()->user()?->can('view', $r))
                    ->visible(fn (PengajuanSurat $record): bool => $record->status === 'diterbitkan')
                    ->action(function (PengajuanSurat $record, PdfSuratGenerator $generator) {
                        abort_unless($record->status === 'diterbitkan', 409);

                        return $generator->downloadResmi($record);
                    }),
                ViewAction::make()
                    ->label('Lihat pengajuan')
                    ->iconButton()
                    ->tooltip('Lihat Detail Pengajuan'),
            ])
            ->emptyStateHeading('Belum ada pengajuan surat')
            ->emptyStateDescription(fn (): string => auth()->user()?->role === 'warga'
                ? 'Pilih Buat Pengajuan Surat Baru untuk mengajukan surat pertama Anda.'
                : 'Pengajuan surat warga akan tampil di sini.')
            ->recordUrl(fn (PengajuanSurat $record): string => static::getUrl('view', ['record' => $record]))
            ->toolbarActions([]);
    }

    private static function labelStatusPengajuan(string $status): string
    {
        return match ($status) {
            'diajukan' => 'Menunggu verifikasi petugas',
            'diverifikasi' => 'Menunggu tanda tangan Wali Nagari',
            'diterbitkan' => 'Selesai diterbitkan',
            'ditolak' => 'Berkas ditolak',
            'dibatalkan' => 'Dibatalkan',
            default => $status,
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pengajuan')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextEntry::make('jenisSurat.nama_surat')->label('Jenis Surat'),
                            TextEntry::make('status')
                                ->label('Status Pengajuan')
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'diajukan' => 'Menunggu Verifikasi',
                                    'diverifikasi' => 'Menunggu Tanda Tangan',
                                    'diterbitkan' => 'Selesai Diterbitkan',
                                    'ditolak' => 'Berkas Ditolak',
                                    'dibatalkan' => 'Dibatalkan',
                                    default => $state,
                                }),
                            TextEntry::make('nomor_surat_final')->label('Nomor Surat Resmi')->placeholder('Belum terbit'),
                            TextEntry::make('created_at')->label('Waktu Diajukan')->dateTime('d F Y, H:i'),
                            TextEntry::make('tahap_berikutnya')
                                ->label('Tahap berikutnya')
                                ->state(fn (PengajuanSurat $record): string => match ($record->status) {
                                    'diajukan' => 'Petugas Nagari sedang memeriksa berkas.',
                                    'diverifikasi' => 'Menunggu tanda tangan Wali Nagari.',
                                    'diterbitkan' => 'Surat selesai dan dapat diunduh.',
                                    'ditolak' => 'Periksa alasan penolakan di bawah.',
                                    'dibatalkan' => 'Pengajuan ini Anda batalkan dan tidak diproses.',
                                    default => 'Pantau perkembangan pengajuan Anda.',
                                })
                                ->columnSpanFull(),
                            TextEntry::make('catatan_penolakan')
                                ->label('Alasan Penolakan')
                                ->visible(fn (PengajuanSurat $r): bool => $r->status === 'ditolak')
                                ->color('danger'),
                        ]),
                    ])
                    ->columnSpanFull(),

                ViewEntry::make('rincian_pengajuan')
                    ->hiddenLabel()
                    ->view('filament.verifikasi.rincian-pengajuan')
                    ->columnSpanFull(),
            ]);
    }

    public static function buildFormFieldComponent($field, bool $isOptionalGroup = false)
    {
        $fieldName = 'data_isian.'.$field->nama_field;
        $label = $field->label.($isOptionalGroup && $field->wajib ? ' (wajib bila disertakan)' : '');
        $wajib = (bool) $field->wajib;

        switch ($field->tipe_field) {
            case 'text':
                $c = self::denganFormatIsian(TextInput::make($fieldName)->label($label)->maxLength(255), $field->format_isian);
                $c->required($wajib);

                return self::withFieldCondition($c, $field);

            case 'textarea':
                $c = Textarea::make($fieldName)->label($label)->rows(3)->maxLength(1000);
                $c->required($wajib);

                return self::withFieldCondition($c, $field);

            case 'number':
                $c = TextInput::make($fieldName)
                    ->label($label)
                    ->numeric()
                    ->minValue(0)
                    ->required($wajib);

                return self::withFieldCondition($c, $field);

            case 'date':
                $c = self::denganFormatIsian(DatePicker::make($fieldName)->label($label), $field->format_isian);
                $c->required($wajib);

                return self::withFieldCondition($c, $field);

            case 'select':
                $options = MasterReferensiHelper::getOptionsForField($field->referensi_master, $field->nama_field, $field->opsi_pilihan);

                return self::withFieldCondition(Select::make($fieldName)->label($label)->options($options)->searchable()->preload()->optionsLimit(150)->required($wajib)->live(), $field);

            case 'rich_text':
                return self::withFieldCondition(RichEditor::make($fieldName)->label($label)->maxLength(1000)->required($wajib), $field);

            case 'table_repeater':
                $subComponents = [];
                foreach ($field->kolomTabels as $kolom) {
                    $subName = $kolom->nama_kolom;
                    $subLabel = $kolom->label;
                    switch ($kolom->tipe_kolom) {
                        case 'number':
                            $subComponents[] = TextInput::make($subName)->label($subLabel)->numeric()->required($kolom->wajib ?? true);
                            break;
                        case 'date':
                            $subComponents[] = self::denganFormatIsian(DatePicker::make($subName)->label($subLabel), $kolom->format_isian)->required($kolom->wajib ?? true);
                            break;
                        case 'select':
                            $subOptions = MasterReferensiHelper::getOptionsForField($kolom->referensi_master, $kolom->nama_kolom, $kolom->opsi_pilihan);
                            $subComponents[] = Select::make($subName)->label($subLabel)->options($subOptions)->searchable()->preload()->optionsLimit(150)->required($kolom->wajib ?? true);
                            break;
                        default:
                            $subComponents[] = self::denganFormatIsian(TextInput::make($subName)->label($subLabel)->maxLength(255), $kolom->format_isian)->required($kolom->wajib ?? true);
                    }
                }
                $c = Repeater::make($fieldName)
                    ->label($label)
                    ->schema($subComponents)
                    ->columns(['default' => 1, 'md' => count($subComponents) > 2 ? 3 : 2])
                    ->addActionLabel("+ Tambah Baris {$label}");

                if (! $wajib) {
                    $c->default([])->required(false);
                } else {
                    $c->required();
                }

                return self::withFieldCondition($c, $field);

            case 'file':
                return self::withFieldCondition(FileUpload::make($fieldName)
                    ->label($label)
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->validationMessages([
                        'mimetypes' => 'Berkas harus berupa PDF, JPG, atau PNG.',
                        'max' => 'Ukuran berkas maksimal 5 MB.',
                    ])
                    ->maxSize(5120)
                    ->helperText('Format: PDF, JPG, PNG (Maksimal 5 MB)')
                    ->directory('lampiran-pengajuan')
                    ->disk('local')
                    ->visibility('private')
                    ->required($wajib && self::berkasWajib()), $field);

            default:
                return self::withFieldCondition(TextInput::make($fieldName)->label($label)->required($wajib), $field);
        }
    }

    /**
     * Aturan isian sesuai "cara menjawab" yang dipilih admin; aturan yang sama diperiksa ulang saat pengajuan disimpan.
     *
     * @template T of TextInput|DatePicker
     *
     * @param  T  $component
     * @return T
     */
    private static function denganFormatIsian(TextInput|DatePicker $component, ?string $format): TextInput|DatePicker
    {
        if ($component instanceof DatePicker) {
            return $format === CaraMenjawab::FORMAT_TANGGAL_LAMPAU
                ? $component->maxDate(now())->validationMessages(['before_or_equal' => ':Attribute tidak boleh melebihi tanggal hari ini.'])
                : $component;
        }

        return match ($format) {
            CaraMenjawab::FORMAT_NIK => $component->length(16)
                ->regex('/^[0-9]{16}$/')
                ->extraInputAttributes(['inputmode' => 'numeric', 'pattern' => '[0-9]*'])
                ->validationMessages(['size' => ':Attribute harus terdiri dari 16 angka.', 'regex' => ':Attribute harus terdiri dari 16 angka.']),
            CaraMenjawab::FORMAT_TELEPON => $component->tel()->regex('/^[0-9\+\-\s\(\)]{8,20}$/'),
            CaraMenjawab::FORMAT_EMAIL => $component->email(),
            default => $component,
        };
    }

    private static function withFieldCondition(Field $component, SkemaFormField $field): Field
    {
        if (($field->kondisi_tipe ?? 'selalu') === 'selalu') {
            return $component;
        }

        $applies = fn (Get $get): bool => app(KondisiFormEvaluator::class)->berlaku(
            $field->kondisi_tipe,
            $field->kondisi_kunci,
            $field->kondisi_nilai,
            (array) $get('data_isian'),
        );

        return $component->visible($applies)->required(fn (Get $get): bool => (bool) $field->wajib
            && $applies($get)
            && ($field->tipe_field !== 'file' || self::berkasWajib()));
    }

    /**
     * Pengajuan petugas langsung diverifikasi, sehingga petugas memilih nomor di sini seperti saat
     * memverifikasi pengajuan warga. Bila belum dapat diverifikasi, nomor dipilih nanti saat verifikasi.
     */
    private static function bagianNomorSurat(): Section
    {
        $jenisSurat = fn (Get $get): ?JenisSurat => JenisSurat::find($get('jenis_surat_id'));

        return Section::make('Nomor surat')
            ->description(fn (): ?string => app(KesiapanPenandatanganan::class)->kendalaVerifikasi() === []
                ? 'Diusulkan otomatis dengan aturan yang sama seperti verifikasi pengajuan warga. Ubah bila perlu; Wali Nagari masih dapat menggantinya sebelum menandatangani.'
                : null)
            ->visible(fn (Get $get): bool => ! self::berkasWajib() && filled($get('jenis_surat_id')))
            ->schema(fn (): array => ($kendala = app(KesiapanPenandatanganan::class)->kendalaVerifikasi()) !== []
                ? [
                    Placeholder::make('nomor_ditunda')
                        ->hiddenLabel()
                        ->content(function () use ($kendala): HtmlString {
                            $nagari = Nagari::query()->first();
                            $tautan = ! app(KesiapanPenandatanganan::class)->stempelTersedia() && $nagari && auth()->user()?->can('update', $nagari)
                                ? ' <a href="'.e(NagariResource::getUrl('edit', ['record' => $nagari])).'" class="font-semibold text-primary-600 underline">Unggah stempel</a>'
                                : '';

                            return new HtmlString(e('Pengajuan akan menunggu di Antrean Verifikasi dan nomor dipilih saat verifikasi. '.implode(' ', $kendala)).$tautan);
                        }),
                ]
                : [
                    TextInput::make('nomor_surat_usulan')
                        ->label('Nomor surat lengkap')
                        ->required()
                        ->maxLength(150)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($jenisSurat): void {
                            if (! is_string($state) || ! ($jenis = $jenisSurat($get))) {
                                return;
                            }

                            $generator = app(NomorSuratGenerator::class);
                            $nomorUrut = rescue(fn (): ?int => $generator->nomorUrutDariNomorLengkap($generator->pengajuanSementara($jenis), $state), report: false);
                            if ($nomorUrut !== null) {
                                $set('nomor_urut_usulan', $nomorUrut);
                            }
                        })
                        ->helperText('Seluruh kode dapat disesuaikan. Angka urut di dalam nomor ini disinkronkan ke kolom berikutnya.'),
                    TextInput::make('nomor_urut_usulan')
                        ->label('Angka urut untuk kelanjutan otomatis')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(NomorSuratGenerator::MAX_NOMOR_URUT)
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($jenisSurat): void {
                            if (filter_var($state, FILTER_VALIDATE_INT) === false || (int) $state < 1 || ! ($jenis = $jenisSurat($get))) {
                                return;
                            }

                            $generator = app(NomorSuratGenerator::class);
                            $set('nomor_surat_usulan', $generator->formatUsulan($generator->pengajuanSementara($jenis), (int) $state));
                        })
                        ->helperText('Nilai ini menentukan nomor otomatis berikutnya dan mengikuti angka urut pada nomor lengkap.'),
                ]);
    }

    /**
     * Usulan dihitung saat petugas masuk ke langkah terakhir agar memakai nomor terbaru; pilihan petugas tidak ditimpa.
     */
    private static function isiUsulanNomor(Get $get, Set $set): void
    {
        $jenisSurat = JenisSurat::find($get('jenis_surat_id'));
        if (self::berkasWajib() || filled($get('nomor_surat_usulan')) || ! $jenisSurat) {
            return;
        }

        $generator = app(NomorSuratGenerator::class);
        $usulan = rescue(fn (): array => $generator->usulanAktif($generator->pengajuanSementara($jenisSurat)), report: false);
        if ($usulan !== null) {
            $set('nomor_surat_usulan', $usulan['nomor_lengkap']);
            $set('nomor_urut_usulan', $usulan['nomor_urut']);
        }
    }

    /**
     * Nomor pilihan petugas diperiksa sebelum pengajuan disimpan, agar bentrokan tampil di isian nomor.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function periksaNomorPilihan(array $data): void
    {
        $jenisSurat = JenisSurat::find($data['jenis_surat_id'] ?? null);
        if (! $jenisSurat || blank($data['nomor_surat_usulan'] ?? null) || app(KesiapanPenandatanganan::class)->kendalaVerifikasi() !== []) {
            return;
        }

        $generator = app(NomorSuratGenerator::class);
        $sementara = $generator->pengajuanSementara($jenisSurat);

        try {
            $nomorUrut = $generator->nomorUrutDariNomorLengkap($sementara, (string) $data['nomor_surat_usulan'])
                ?? (int) ($data['nomor_urut_usulan'] ?? 0);
            $generator->pastikanUsulanTersedia($sementara, $nomorUrut, nomorSurat: (string) $data['nomor_surat_usulan']);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['data.nomor_surat_usulan' => $exception->getMessage()]);
        }
    }

    /**
     * Penolakan saat mengirim juga tampil sebagai notifikasi, karena kolom yang bermasalah bisa berada di langkah wizard lain.
     *
     * @throws ValidationException
     */
    public static function beritahuPenolakan(ValidationException $exception): never
    {
        Notification::make()
            ->title('Pengajuan belum dapat dikirim')
            ->body(implode(' ', Arr::flatten($exception->errors())))
            ->danger()
            ->persistent()
            ->send();

        throw $exception;
    }

    /**
     * Unggahan berkas hanya wajib bagi warga; petugas memeriksa dokumen fisik pemohon secara langsung.
     */
    private static function berkasWajib(): bool
    {
        return auth()->user()?->role === 'warga';
    }

    private static function pemohonTerpilih(?User $user, Get $get): ?Penduduk
    {
        $nik = $user?->role === 'warga' ? $user->penduduk_nik : $get('penduduk_nik');

        return filled($nik) ? Penduduk::find((string) $nik) : null;
    }

    /**
     * Jenis surat yang masih diproses (diajukan/diverifikasi) atas nama pemohon terpilih.
     *
     * @return array<int, int>
     */
    private static function jenisSuratSedangDiproses(Get $get): array
    {
        $user = auth()->user();
        $nik = $user?->role === 'warga' ? $user->penduduk_nik : $get('penduduk_nik');

        if (blank($nik)) {
            return [];
        }

        return PengajuanSurat::query()
            ->where('penduduk_nik', (string) $nik)
            ->whereIn('status', ['diajukan', 'diverifikasi'])
            ->distinct()
            ->pluck('jenis_surat_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengajuanWargas::route('/'),
            'create' => CreatePengajuanWarga::route('/create'),
            'view' => ViewPengajuanWarga::route('/{record}'),
        ];
    }
}
