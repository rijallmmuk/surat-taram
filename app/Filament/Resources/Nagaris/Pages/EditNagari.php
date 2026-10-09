<?php

namespace App\Filament\Resources\Nagaris\Pages;

use App\Filament\Resources\Nagaris\NagariResource;
use App\Services\AuditLogService;
use Filament\Resources\Pages\EditRecord;

class EditNagari extends EditRecord
{
    protected static string $resource = NagariResource::class;

    public function getHeading(): string
    {
        return 'Kop Surat & Profil Nagari Taram';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    protected ?bool $hasDatabaseTransactions = true;

    private ?string $stempelSebelum = null;

    protected function beforeSave(): void
    {
        $this->stempelSebelum = $this->record->stempel_path;
    }

    protected function afterSave(): void
    {
        if ($this->stempelSebelum === $this->record->stempel_path) {
            return;
        }

        app(AuditLogService::class)->record(
            actor: auth()->user(),
            action: 'ubah_stempel_nagari',
            targetType: 'Nagari',
            targetId: $this->record->id,
            description: 'Stempel resmi Nagari untuk seluruh jenis surat diperbarui.',
            before: ['stempel_path' => $this->stempelSebelum],
            after: ['stempel_path' => $this->record->stempel_path],
        );
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/panel') => 'Beranda',
            $this->getHeading(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
