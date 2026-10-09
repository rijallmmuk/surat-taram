<?php

namespace App\Filament\Resources\PejabatNagaris\Pages;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\PejabatNagaris\PejabatNagariResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EditPejabatNagari extends EditRecord
{
    protected static string $resource = PejabatNagariResource::class;

    protected static ?string $title = 'Ubah Data Pejabat Nagari';

    protected function getHeaderActions(): array
    {
        return [
            DeleteWithReasonAction::make()
                ->successRedirectUrl(PejabatNagariResource::getUrl()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getRecord()->user;
        if ($user) {
            $data['username'] = $user->username;
            $data['email'] = $user->email;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $role = match ($data['jabatan'] ?? $record->jabatan) {
                'sekretaris_nagari' => 'sekretaris',
                default => 'wali_nagari',
            };

            $user = $record->user;
            $statusAktif = isset($data['status_aktif']) ? (bool) $data['status_aktif'] : $record->status_aktif;
            $namaPejabat = $data['nama_pejabat'] ?? $record->nama_pejabat;

            $userData = [
                'name' => $namaPejabat,
                'role' => $role,
                'is_active' => $statusAktif,
            ];

            if (! empty($data['username'])) {
                $userData['username'] = $data['username'];
            }
            if (array_key_exists('email', $data)) {
                $userData['email'] = $data['email'];
            }
            if (! empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }

            if ($user) {
                $user->update($userData);
            } else {
                $userData['username'] = $data['username'];
                $userData['password'] = Hash::make($data['password']);
                $user = User::create($userData);
                $data['user_id'] = $user->id;
            }

            if (($data['jabatan'] ?? $record->jabatan) === 'sekretaris_nagari') {
                $data['file_tanda_tangan_path'] = null;
            }

            unset($data['username'], $data['email'], $data['password']);

            $record->update($data);

            return $record;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
