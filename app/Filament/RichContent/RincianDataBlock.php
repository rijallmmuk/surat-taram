<?php

namespace App\Filament\RichContent;

use App\Services\TemplatSurat;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

/**
 * Daftar "label : nilai" dengan tata letak baku surat Nagari (kolom 28% : 3% : 69%, menjorok 15px).
 */
class RincianDataBlock extends BlokSurat
{
    public static function getId(): string
    {
        return TemplatSurat::BLOK_RINCIAN;
    }

    public static function getLabel(): string
    {
        return 'Rincian data';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalDescription('Setiap baris dicetak sebagai "Tulisan : data" dengan tata letak surat resmi.')
            ->schema([
                Repeater::make('baris')
                    ->label('Baris rincian')
                    ->addActionLabel('Tambah baris')
                    ->reorderable()
                    ->minItems(1)
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('label')->label('Tulisan di kiri')->placeholder('Contoh: Nama Usaha')->required()->maxLength(100),
                            ...static::isianIsi(fn (): array => static::tagEditor($action), 'Data yang ditampilkan'),
                        ]),
                        Toggle::make('tebal')->label('Cetak tebal'),
                    ]),
                ...static::isianKondisi($action),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        return 'Rincian data'.static::keteranganKondisi($config);
    }

    public static function toPreviewHtml(array $config): ?string
    {
        $html = '';
        foreach ((array) ($config['baris'] ?? []) as $baris) {
            $isi = static::chipData(static::labelTag($baris['isi'] ?? null))->toHtml();
            if (filled($baris['isi_lanjutan'] ?? null)) {
                $isi .= e((string) ($baris['pemisah'] ?? '')).static::chipData(static::labelTag($baris['isi_lanjutan']))->toHtml();
            }
            $html .= '<tr><td style="width: 28%; padding: 2px 6px;">'.e((string) ($baris['label'] ?? '')).'</td><td style="width: 3%;">:</td><td style="padding: 2px 6px;">'.$isi.'</td></tr>';
        }

        return '<table style="width: 100%;"><tbody>'.$html.'</tbody></table>';
    }

    public static function toHtml(array $config, array $data): ?string
    {
        if (! static::tampil($config, $data)) {
            return '';
        }

        $nilai = (array) ($data['nilai'] ?? []);
        $html = '';
        foreach (array_values((array) ($config['baris'] ?? [])) as $indeks => $baris) {
            if (! is_array($baris)) {
                continue;
            }

            $isi = static::gabungkan($nilai, $baris['isi'] ?? null, $baris['pemisah'] ?? null, $baris['isi_lanjutan'] ?? null);
            if (! empty($baris['tebal'])) {
                $isi = '<strong>'.$isi.'</strong>';
            }

            $lebar = $indeks === 0 ? ['width: 28%;', 'width: 3%; text-align: center;', 'width: 69%;'] : ['', 'text-align: center;', ''];
            $html .= '<tr>'
                .'<td style="'.$lebar[0].'">'.e((string) ($baris['label'] ?? '')).'</td>'
                .'<td style="'.$lebar[1].'">:</td>'
                .'<td style="'.$lebar[2].'">'.$isi.'</td>'
                .'</tr>';
        }

        return $html === '' ? '' : '<table style="width: 97%; margin-left: 15px;"><tbody>'.$html.'</tbody></table>';
    }
}
