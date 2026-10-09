<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyaratDokumen extends Model
{
    use HasFactory;

    protected $table = 'syarat_dokumen';

    protected $fillable = [
        'jenis_surat_id',
        'master_syarat_dokumen_id',
        'nama_dokumen',
        'wajib',
        'keterangan',
        'kondisi_tipe',
        'kondisi_kunci',
        'kondisi_nilai',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SyaratDokumen $syarat) {
            if (filled($syarat->nama_dokumen)) {
                $cleanNama = trim($syarat->nama_dokumen);
                $syarat->nama_dokumen = $cleanNama;

                $master = MasterSyaratDokumen::firstOrCreate(
                    ['nama_dokumen' => $cleanNama],
                    [
                        'keterangan_default' => $syarat->keterangan,
                        'wajib_default' => $syarat->wajib ?? true,
                    ]
                );

                $syarat->master_syarat_dokumen_id = $master->id;

                if (blank($syarat->keterangan) && filled($master->keterangan_default)) {
                    $syarat->keterangan = $master->keterangan_default;
                }
            }
        });
    }

    public function jenisSurat(): BelongsTo
    {
        return $this->belongsTo(JenisSurat::class, 'jenis_surat_id');
    }

    public function masterSyaratDokumen(): BelongsTo
    {
        return $this->belongsTo(MasterSyaratDokumen::class, 'master_syarat_dokumen_id');
    }
}
