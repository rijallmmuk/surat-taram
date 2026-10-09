<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenWarga extends Model
{
    use HasFactory;

    protected $table = 'dokumen_warga';

    protected $fillable = [
        'penduduk_nik',
        'master_syarat_dokumen_id',
        'nama_dokumen',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'penduduk_nik', 'nik');
    }

    public function masterSyaratDokumen(): BelongsTo
    {
        return $this->belongsTo(MasterSyaratDokumen::class, 'master_syarat_dokumen_id');
    }
}
