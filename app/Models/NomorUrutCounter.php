<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NomorUrutCounter extends Model
{
    /**
     * Baris `kunci_penerbitan` yang dikunci setiap kali nomor surat dipesan (lihat NomorSuratGenerator::kunciPenomoran).
     */
    public const ID_KUNCI_PENOMORAN = 1;

    public $timestamps = false;

    protected $table = 'nomor_urut_counters';

    protected $fillable = [
        'scope_type',
        'scope_key',
        'jenis_surat_id',
        'tahun',
        'nomor_terakhir',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'nomor_terakhir' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function jenisSurat(): BelongsTo
    {
        return $this->belongsTo(JenisSurat::class, 'jenis_surat_id');
    }
}
