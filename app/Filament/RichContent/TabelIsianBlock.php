<?php

namespace App\Filament\RichContent;

use App\Services\KatalogTagSurat;
use App\Services\TemplatSurat;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Tabel berbingkai berisi baris jawaban "isi beberapa baris data" (mis. ahli waris, tanggungan).
 * Tabel tanpa baris tidak dicetak.
 */
class TabelIsianBlock extends BlokSurat
{
    public static function getId(): string
    {
        return TemplatSurat::BLOK_TABEL;
    }

    public static function getLabel(): string
    {
        return 'Tabel isian';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalDescription('Setiap baris jawaban warga menjadi satu baris tabel.')
            ->schema([
                Select::make('isian')
                    ->label('Data tabel dari pertanyaan')
                    ->options(fn (): array => app(KatalogTagSurat::class)->tabel(static::skemaEditor($action)))
                    ->required()
                    ->live(),
                Toggle::make('nomor')->label('Tampilkan kolom No')->default(true),
                Repeater::make('kolom')
                    ->label('Kolom tabel')
                    ->addActionLabel('Tambah kolom')
                    ->reorderable()
                    ->minItems(1)
                    ->itemLabel(fn (array $state): ?string => $state['judul'] ?? null)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('judul')->label('Judul kolom')->required()->maxLength(100),
                            ...static::isianIsi(fn (Get $get): array => app(KatalogTagSurat::class)->nilaiKolomTabel(static::skemaEditor($action), (string) $get('../../isian')), 'Data yang ditampilkan'),
                            TextInput::make('lebar')
                                ->label('Lebar kolom (%, boleh dikosongkan)')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(100),
                            Select::make('rata')
                                ->label('Perataan isi')
                                ->options(['kiri' => 'Rata kiri', 'tengah' => 'Rata tengah'])
                                ->default('kiri')
                                ->required(),
                        ]),
                    ]),
                ...static::isianKondisi($action),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        return 'Tabel isian: '.static::labelTag('isian.'.($config['isian'] ?? '')).static::keteranganKondisi($config);
    }

    public static function toPreviewHtml(array $config): ?string
    {
        $kepala = ! empty($config['nomor'] ?? true) ? '<th style="border: 1px solid #94a3b8; padding: 2px 6px;">No</th>' : '';
        foreach ((array) ($config['kolom'] ?? []) as $kolom) {
            $kepala .= '<th style="border: 1px solid #94a3b8; padding: 2px 6px;">'.e((string) ($kolom['judul'] ?? '')).'</th>';
        }

        return '<table style="width: 100%; border-collapse: collapse;"><tr>'.$kepala.'</tr></table>';
    }

    public static function toHtml(array $config, array $data): ?string
    {
        $kodeTabel = (string) ($config['isian'] ?? '');
        $baris = array_values(array_filter((array) ($data['data_isian'][$kodeTabel] ?? []), 'is_array'));
        if ($baris === [] || ! static::tampil($config, $data)) {
            return '';
        }

        $katalog = app(KatalogTagSurat::class);
        $skema = (array) ($data['skema'] ?? []);
        $kolom = array_values(array_filter((array) ($config['kolom'] ?? []), 'is_array'));
        $nomor = ! array_key_exists('nomor', $config) || ! empty($config['nomor']);
        $garis = 'border: 1px solid #333;';

        $kepala = $nomor ? '<th style="'.$garis.' text-align: center; width: 5%;">No</th>' : '';
        foreach ($kolom as $item) {
            $lebar = filled($item['lebar'] ?? null) ? ' width: '.(int) $item['lebar'].'%;' : '';
            $kepala .= '<th style="'.$garis.' text-align: center;'.$lebar.'">'.e((string) ($item['judul'] ?? '')).'</th>';
        }

        $isi = '';
        foreach ($baris as $indeks => $barisData) {
            $isi .= '<tr>'.($nomor ? '<td style="'.$garis.' text-align: center;">'.($indeks + 1).'</td>' : '');
            foreach ($kolom as $item) {
                $nilai = [
                    'isi' => $katalog->nilaiSel($skema, $kodeTabel, (string) ($item['isi'] ?? ''), $barisData),
                    'lanjutan' => filled($item['isi_lanjutan'] ?? null)
                        ? $katalog->nilaiSel($skema, $kodeTabel, (string) $item['isi_lanjutan'], $barisData)
                        : '',
                ];
                $rata = ($item['rata'] ?? 'kiri') === 'tengah' ? 'center' : 'left';
                $isi .= '<td style="'.$garis.' text-align: '.$rata.';">'.static::gabungkan($nilai, 'isi', $item['pemisah'] ?? null, 'lanjutan').'</td>';
            }
            $isi .= '</tr>';
        }

        return '<table style="width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 8px;">'
            .'<thead><tr style="background-color: #f0f0f0;">'.$kepala.'</tr></thead>'
            .'<tbody>'.$isi.'</tbody></table>';
    }
}
