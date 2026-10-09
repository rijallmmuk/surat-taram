<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JenisSurat extends Model
{
    use HasFactory;

    protected $table = 'jenis_surat';

    protected $fillable = [
        'nama_surat',
        'kode_klasifikasi',
        'kode_unit',
        'pola_format_nomor',
        'mode_counter',
        'reset_counter',
        'padding_digit',
        'status',
        'urutan_tampil',
    ];

    protected function casts(): array
    {
        return [
            'padding_digit' => 'integer',
            'urutan_tampil' => 'integer',
        ];
    }

    public function skemaFormFields(): HasMany
    {
        return $this->hasMany(SkemaFormField::class, 'jenis_surat_id')->orderBy('urutan');
    }

    public function templateSurat(): HasOne
    {
        return $this->hasOne(TemplateSurat::class, 'jenis_surat_id')->where('status_aktif', true);
    }

    public function templateSurats(): HasMany
    {
        return $this->hasMany(TemplateSurat::class, 'jenis_surat_id');
    }

    public function syaratDokumens(): HasMany
    {
        return $this->hasMany(SyaratDokumen::class, 'jenis_surat_id')->orderBy('urutan');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(NomorUrutCounter::class, 'jenis_surat_id');
    }

    public function pengajuanSurats(): HasMany
    {
        return $this->hasMany(PengajuanSurat::class, 'jenis_surat_id');
    }
}
