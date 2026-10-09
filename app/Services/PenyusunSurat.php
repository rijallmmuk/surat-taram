<?php

namespace App\Services;

use App\Filament\RichContent\BagianBersyaratBlock;
use App\Filament\RichContent\BlokSurat;
use App\Filament\RichContent\RincianDataBlock;
use App\Filament\RichContent\TabelIsianBlock;
use App\Models\Penduduk;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * Menyusun HTML isi surat dari dokumen editor, isian warga, dan data pemohon.
 * Builder, pratinjau, simulasi, draf, dan surat terbit memakai penyusun yang sama.
 */
class PenyusunSurat
{
    /** @var list<class-string<BlokSurat>> */
    public const BLOK = [RincianDataBlock::class, TabelIsianBlock::class, BagianBersyaratBlock::class];

    public function __construct(private KatalogTagSurat $katalog) {}

    /**
     * @param  array<string, mixed>  $dokumen
     * @param  iterable<mixed>  $skema
     * @param  array<string, mixed>  $dataIsian
     * @param  array{nomor_surat?: ?string, tanggal_surat?: ?string}  $meta
     */
    public function susun(array $dokumen, iterable $skema, array $dataIsian, ?Penduduk $penduduk, array $meta = []): string
    {
        $skema = $this->katalog->normalisasiSkema($skema);
        $nilai = $this->katalog->nilai($skema, $dataIsian, $penduduk, $meta);

        // Tag yang tidak lagi dikenal dicetak kosong; pemeriksaan aktivasi mencegahnya sebelum surat dipakai.
        foreach (TemplatSurat::tagDipakai($dokumen) as $tag) {
            $nilai[$tag] ??= '';
        }

        $konteks = ['skema' => $skema, 'data_isian' => $dataIsian, 'nilai' => $nilai];

        return RichContentRenderer::make($dokumen)
            ->mergeTags($nilai)
            ->customBlocks(array_fill_keys(self::BLOK, $konteks))
            ->toHtml();
    }
}
