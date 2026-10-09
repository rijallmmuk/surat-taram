<?php

namespace App\Filament\Resources\Jorongs\Schemas;

use App\Models\Jorong;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JorongForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_jorong')
                    ->label('Nama Jorong')
                    ->required()
                    ->maxLength(100)
                    ->rules([
                        fn (?Jorong $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $normalized = trim(preg_replace('/^jorong\s+/i', '', (string) $value));

                            if ($normalized === '') {
                                $fail('Nama jorong wajib diisi.');

                                return;
                            }

                            $query = Jorong::where('nama_jorong', $normalized);
                            if ($record) {
                                $query->whereKeyNot($record->getKey());
                            }

                            if ($query->exists()) {
                                $fail('Nama jorong sudah digunakan.');
                            }
                        },
                    ])
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? trim(preg_replace('/^jorong\s+/i', '', $state)) : null)
                    ->columnSpanFull(),
            ]);
    }
}
