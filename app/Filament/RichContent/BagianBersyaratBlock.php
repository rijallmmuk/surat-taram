<?php

namespace App\Filament\RichContent;

use App\Services\TemplatSurat;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * Paragraf yang hanya dicetak bila kondisi terpenuhi (mis. warga menyertakan data ayah).
 */
class BagianBersyaratBlock extends BlokSurat
{
    public static function getId(): string
    {
        return TemplatSurat::BLOK_BERSYARAT;
    }

    public static function getLabel(): string
    {
        return 'Bagian bersyarat';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalWidth('3xl')
            ->schema([
                ...static::isianKondisi($action, wajib: true),
                RichEditor::make('konten')
                    ->label('Isi bagian')
                    ->json()
                    ->required()
                    ->mergeTags(fn (): array => static::tagEditor($action))
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline'],
                        ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                        ['mergeTags'],
                        ['undo', 'redo'],
                    ]),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        return 'Bagian bersyarat'.static::keteranganKondisi($config);
    }

    public static function toPreviewHtml(array $config): ?string
    {
        $konten = $config['konten'] ?? null;
        if (! is_array($konten)) {
            return null;
        }

        $label = [];
        foreach (TemplatSurat::tagDipakai($konten) as $tag) {
            $label[$tag] = static::chipData(static::labelTag($tag));
        }

        return RichContentRenderer::make($konten)->mergeTags($label)->toHtml();
    }

    public static function toHtml(array $config, array $data): ?string
    {
        $konten = $config['konten'] ?? null;
        if (! is_array($konten) || ! static::tampil($config, $data)) {
            return '';
        }

        return RichContentRenderer::make($konten)
            ->mergeTags((array) ($data['nilai'] ?? []))
            ->toUnsafeHtml();
    }
}
