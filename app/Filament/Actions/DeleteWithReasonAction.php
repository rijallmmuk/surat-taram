<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Forms\Components\Textarea;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;

class DeleteWithReasonAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'delete';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Hapus');
        $this->defaultColor('danger');
        $this->tableIcon(FilamentIcon::resolve(ActionsIconAlias::DELETE_ACTION) ?? Heroicon::Trash);
        $this->groupedIcon(FilamentIcon::resolve(ActionsIconAlias::DELETE_ACTION_GROUPED) ?? Heroicon::Trash);
        $this->modalIcon(FilamentIcon::resolve(ActionsIconAlias::DELETE_ACTION_MODAL) ?? Heroicon::OutlinedTrash);
        $this->requiresConfirmation();
        $this->modalSubmitActionLabel('Hapus');
        $this->modalCancelActionLabel(fn (Model $record): string => Gate::allows('delete', $record) ? 'Batal' : 'Tutup');
        $this->modalDescription(function (Model $record): string {
            $response = Gate::inspect('delete', $record);

            return $response->allowed()
                ? 'Data akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.'
                : ($response->message() ?: 'Data ini tidak dapat dihapus karena masih digunakan atau dilindungi oleh sistem.');
        });
        $this->modalSubmitAction(fn (Action $action, Model $record): Action|false => Gate::allows('delete', $record) ? $action : false);
        $this->visible(fn (Model $record): bool => Gate::allows('deleteAny', $record::class));
        $this->schema(fn (Model $record): array => Gate::allows('delete', $record) ? [
            Textarea::make('alasan')
                ->label('Alasan penghapusan')
                ->required()
                ->maxLength(500)
                ->rows(3),
        ] : []);
        $this->successNotificationTitle('Data berhasil dihapus');
        $this->action(function (Model $record, array $data): void {
            Gate::authorize('delete', $record);
            Context::add('alasan_hapus', trim((string) ($data['alasan'] ?? '')));

            try {
                $record->delete();
            } finally {
                Context::forget('alasan_hapus');
            }

            $this->success();
        });
    }
}
