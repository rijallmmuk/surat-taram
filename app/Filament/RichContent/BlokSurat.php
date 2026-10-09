<?php

namespace App\Filament\RichContent;

use App\Services\KatalogTagSurat;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Dasar blok khusus isi surat. Blok membaca isian dari form builder yang sedang dibuka
 * dan merender dirinya dari konteks cetak: ['skema' => ..., 'data_isian' => ..., 'nilai' => ...].
 */
abstract class BlokSurat extends RichContentCustomBlock
{
    /**
     * Skema isian builder yang sedang dibuka, dibaca melalui editor yang memasang blok ini.
     *
     * @return list<array<string, mixed>>
     */
    protected static function skemaEditor(Action $action): array
    {
        $editor = $action->getSchemaComponent();
        $skema = $editor?->makeGetUtility()('../../skemaFormFields');

        return app(KatalogTagSurat::class)->normalisasiSkema(is_array($skema) ? $skema : []);
    }

    /**
     * @return array<string, string>
     */
    protected static function tagEditor(Action $action): array
    {
        $editor = $action->getSchemaComponent();

        return $editor instanceof RichEditor ? $editor->getMergeTags() : [];
    }

    /**
     * Pengaturan "tampil bila" yang sama untuk setiap blok.
     *
     * @return array<Component>
     */
    protected static function isianKondisi(Action $action, bool $wajib = false): array
    {
        return [
            Select::make('kondisi')
                ->label($wajib ? 'Tampil bila' : 'Tampil hanya bila (kosongkan bila selalu tampil)')
                ->multiple()
                ->options(fn (): array => app(KatalogTagSurat::class)->kondisi(static::skemaEditor($action)))
                ->required($wajib)
                ->live(),
            Radio::make('cocok')
                ->label('Bila memilih lebih dari satu')
                ->options(['semua' => 'Semua harus terpenuhi', 'salah_satu' => 'Cukup salah satu terpenuhi'])
                ->default('semua')
                ->inline()
                ->visible(fn (Get $get): bool => count((array) $get('kondisi')) > 1),
        ];
    }

    /**
     * Isian "isi" sebuah baris/kolom beserta pilihan gabungan (mis. tempat dan tanggal lahir) yang tersembunyi
     * sampai admin membutuhkannya.
     *
     * @param  Closure(Get): array<string, string>  $pilihan
     * @return array<Component>
     */
    protected static function isianIsi(Closure $pilihan, string $labelIsi): array
    {
        return [
            Select::make('isi')
                ->label($labelIsi)
                ->options($pilihan)
                ->searchable()
                ->required(),
            Toggle::make('gabung')
                ->label('Gabungkan dengan data lain (mis. tempat dan tanggal lahir)')
                ->dehydrated(false)
                ->live()
                ->afterStateHydrated(fn (Set $set, Get $get) => $set('gabung', filled($get('isi_lanjutan'))))
                ->afterStateUpdated(function (Set $set, Get $get, bool $state): void {
                    $set('pemisah', $state ? ($get('pemisah') ?: '/ ') : '');
                    if (! $state) {
                        $set('isi_lanjutan', null);
                    }
                })
                ->columnSpanFull(),
            TextInput::make('pemisah')
                ->label('Tanda penghubung')
                ->helperText('Contoh "/ " menghasilkan "Taram/ 01-01-1990".')
                ->maxLength(10)
                ->visible(fn (Get $get): bool => (bool) $get('gabung')),
            Select::make('isi_lanjutan')
                ->label('Data yang digabungkan')
                ->options($pilihan)
                ->searchable()
                ->required(fn (Get $get): bool => (bool) $get('gabung'))
                ->visible(fn (Get $get): bool => (bool) $get('gabung')),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    protected static function tampil(array $config, array $data): bool
    {
        return app(KatalogTagSurat::class)->terpenuhi(
            array_values((array) ($config['kondisi'] ?? [])),
            (string) ($config['cocok'] ?? 'semua'),
            (array) ($data['data_isian'] ?? []),
        );
    }

    /**
     * Nilai gabungan "isi + pemisah + isi lanjutan" sebagai HTML aman.
     *
     * @param  array<string, string|Htmlable>  $nilai
     */
    protected static function gabungkan(array $nilai, ?string $isi, ?string $pemisah, ?string $isiLanjutan): string
    {
        $bagian = array_values(array_filter(
            [self::html($nilai[$isi ?? ''] ?? ''), self::html($nilai[$isiLanjutan ?? ''] ?? '')],
            fn (string $html): bool => trim(strip_tags($html)) !== '',
        ));

        return implode(e((string) $pemisah), $bagian);
    }

    protected static function html(string|Htmlable $nilai): string
    {
        return $nilai instanceof Htmlable ? $nilai->toHtml() : e($nilai);
    }

    /**
     * Label tag yang mudah dibaca untuk pratinjau editor.
     */
    protected static function labelTag(?string $id): string
    {
        if (blank($id)) {
            return '';
        }

        $label = KatalogTagSurat::TAG_PEMOHON[$id] ?? KatalogTagSurat::TAG_SURAT[$id] ?? null;
        if ($label !== null) {
            return $label;
        }

        [, $kode] = array_pad(explode('.', $id, 2), 2, $id);
        [$kode, $format] = array_pad(explode('.', (string) $kode, 2), 2, null);
        $keterangan = match ($format) {
            'angka' => ' (angka)',
            'rupiah' => ' (Rupiah)',
            'inisial' => ' (huruf awal)',
            default => '',
        };

        return (static::labelPertanyaan((string) $kode) ?? Str::headline((string) $kode)).$keterangan;
    }

    /**
     * Teks pertanyaan terbaru dari form builder yang sedang dibuka, agar pratinjau blok tidak
     * menampilkan nama yang diturunkan dari kode isian.
     */
    protected static function labelPertanyaan(string $kode): ?string
    {
        $skema = data_get(Livewire::current(), 'data.skemaFormFields');
        if (! is_array($skema)) {
            return null;
        }

        foreach ($skema as $field) {
            if (is_array($field) && ($field['nama_field'] ?? null) === $kode && filled($field['label'] ?? null)) {
                return (string) $field['label'];
            }
        }

        return null;
    }

    /**
     * Data yang akan diisi otomatis, ditampilkan sebagai label berwarna di pratinjau blok.
     */
    protected static function chipData(string $label): HtmlString
    {
        return new HtmlString('<span style="background-color: #e0f2fe; color: #075985; border-radius: 4px; padding: 0 4px;">'.e($label).'</span>');
    }

    /**
     * Keterangan kondisi tampil yang mudah dibaca untuk judul blok di editor.
     *
     * @param  array<string, mixed>  $config
     */
    protected static function keteranganKondisi(array $config): string
    {
        $kondisi = array_values(array_filter((array) ($config['kondisi'] ?? []), 'is_string'));
        if ($kondisi === []) {
            return '';
        }

        $label = array_map(function (string $item): string {
            [$jenis, $isi] = array_pad(explode(':', $item, 2), 2, '');

            return match ($jenis) {
                'kelompok' => 'warga menyertakan '.Str::headline($isi),
                'diisi' => (static::labelPertanyaan($isi) ?? Str::headline($isi)).' diisi',
                'pilihan' => (function () use ($isi): string {
                    [$kode, $nilai] = array_pad(explode('=', $isi, 2), 2, '');

                    return (static::labelPertanyaan($kode) ?? Str::headline($kode)).' = '.$nilai;
                })(),
                default => $item,
            };
        }, $kondisi);

        return ' — tampil bila '.implode(($config['cocok'] ?? 'semua') === 'salah_satu' ? ' atau ' : ' dan ', $label);
    }
}
