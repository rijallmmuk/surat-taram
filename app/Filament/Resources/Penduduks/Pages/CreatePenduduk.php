<?php

namespace App\Filament\Resources\Penduduks\Pages;

use App\Filament\Resources\Penduduks\PendudukResource;
use App\Models\Penduduk;
use App\Services\WargaAccountService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreatePenduduk extends CreateRecord
{
    protected static string $resource = PendudukResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['nik'])) {
            $str = (string) $data['nik'];
            $data['nik'] = is_numeric($str) && str_contains($str, 'E')
                ? sprintf('%.0f', (float) $str)
                : preg_replace('/\D/', '', $str);
        }

        if (! empty($data['kk_number'])) {
            $str = (string) $data['kk_number'];
            $data['kk_number'] = is_numeric($str) && str_contains($str, 'E')
                ? sprintf('%.0f', (float) $str)
                : preg_replace('/\D/', '', $str);
        }

        if (! empty($data['tanggal_lahir'])) {
            $tgl = (string) $data['tanggal_lahir'];
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $tgl)) {
                $data['tanggal_lahir'] = Carbon::createFromFormat('d/m/Y', $tgl)->format('Y-m-d');
            } elseif (preg_match('/^\d{8}$/', $tgl)) {
                $data['tanggal_lahir'] = Carbon::createFromFormat('dmY', $tgl)->format('Y-m-d');
            }
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Penduduk {
            $penduduk = Penduduk::create($data);
            app(WargaAccountService::class)->ensureFor($penduduk);

            return $penduduk;
        });
    }
}
