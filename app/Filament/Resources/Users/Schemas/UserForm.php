<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(1),
                TextInput::make('username')
                    ->label(fn (string $operation): string => $operation === 'create' ? 'Username Login' : 'Username / NIK')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->columnSpan(1),
                TextInput::make('email')
                    ->label('Alamat Email')
                    ->email()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->nullable()
                    ->columnSpan(2),
                Hidden::make('role')
                    ->default('admin')
                    ->required()
                    ->rules(fn (string $operation): array => [$operation === 'create' ? 'in:admin' : 'in:admin,superadmin']),
                TextInput::make('password')
                    ->label('Kata Sandi')
                    ->password()
                    ->revealable()
                    ->rule(User::aturanSandi())
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation): string => $operation === 'edit'
                        ? User::keteranganAturanSandi().' Kosongkan jika tidak ingin mengubah sandi. Pemilik akun dapat menggantinya dari profil.'
                        : User::keteranganAturanSandi().' Pemilik akun dapat menggantinya dari profil.')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Akun Aktif')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record?->getKey() === auth()->id())
                    ->columnSpanFull(),
            ]);
    }
}
