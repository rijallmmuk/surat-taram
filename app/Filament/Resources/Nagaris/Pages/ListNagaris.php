<?php

namespace App\Filament\Resources\Nagaris\Pages;

use App\Filament\Resources\Nagaris\NagariResource;
use App\Models\Nagari;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNagaris extends ListRecords
{
    protected static string $resource = NagariResource::class;

    public function mount(): void
    {
        $nagari = Nagari::first();
        if ($nagari) {
            $this->redirect(NagariResource::getUrl('edit', ['record' => $nagari->getKey()]));

            return;
        }

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Profil Nagari')
                ->visible(fn (): bool => NagariResource::canCreate()),
        ];
    }
}
