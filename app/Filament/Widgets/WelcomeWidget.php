<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PengajuanWargaResource;
use App\Support\Dashboard\WelcomeData;
use Filament\Widgets\Widget;

class WelcomeWidget extends Widget
{
    protected ?string $pollingInterval = null;

    protected string $view = 'filament.widgets.welcome';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            ...WelcomeData::forUser($user),
            'newSubmissionUrl' => $user?->role === 'warga' && PengajuanWargaResource::canCreate()
                ? PengajuanWargaResource::getUrl('create')
                : null,
        ];
    }
}
