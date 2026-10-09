<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Berkas privat (disk local) yang diganti atau datanya dihapus ikut dihapus setelah transaksi tersimpan,
 * agar salinan stempel atau tanda tangan lama tidak tertinggal di server.
 */
trait HapusBerkasPrivatLama
{
    /**
     * @return list<string>
     */
    abstract protected function kolomBerkasPrivat(): array;

    protected static function bootHapusBerkasPrivatLama(): void
    {
        static::updated(function (Model $model): void {
            foreach ($model->kolomBerkasPrivat() as $kolom) {
                if ($model->wasChanged($kolom)) {
                    self::hapusBerkasPrivat($model->getOriginal($kolom));
                }
            }
        });

        static::deleted(function (Model $model): void {
            foreach ($model->kolomBerkasPrivat() as $kolom) {
                self::hapusBerkasPrivat($model->getAttribute($kolom));
            }
        });
    }

    private static function hapusBerkasPrivat(mixed $path): void
    {
        if (is_string($path) && filled($path)) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        }
    }
}
