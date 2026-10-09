<?php

namespace App\Filament\Resources\PejabatNagaris\Pages;

use App\Filament\Resources\PejabatNagaris\PejabatNagariResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreatePejabatNagari extends CreateRecord
{
    protected static string $resource = PejabatNagariResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Tambah Pejabat Nagari';

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $role = match ($data['jabatan'] ?? '') {
                'sekretaris_nagari' => 'sekretaris',
                default => 'wali_nagari',
            };

            $user = User::create([
                'name' => $data['nama_pejabat'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $role,
                'is_active' => (bool) ($data['status_aktif'] ?? true),
            ]);

            $data['user_id'] = $user->id;

            if (($data['jabatan'] ?? '') === 'sekretaris_nagari') {
                $data['file_tanda_tangan_path'] = null;
            }

            unset($data['username'], $data['email'], $data['password']);

            return static::getModel()::create($data);
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
