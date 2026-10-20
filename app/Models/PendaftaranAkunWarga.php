<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendaftaranAkunWarga extends Model
{
    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_TERVERIFIKASI = 'terverifikasi';

    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'user_id',
        'nik',
        'kk_number',
        'nama',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'jorong_id',
        'ref_agama_id',
        'ref_status_kawin_id',
        'ref_pekerjaan_id',
        'ref_pendidikan_id',
        'ref_kewarganegaraan_id',
        'no_hp',
        'file_ktp_path',
        'status',
        'catatan_verifikasi',
        'diverifikasi_oleh_user_id',
        'diverifikasi_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'diverifikasi_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'nik', 'nik');
    }

    public function jorong(): BelongsTo
    {
        return $this->belongsTo(Jorong::class, 'jorong_id');
    }

    public function agama(): BelongsTo
    {
        return $this->belongsTo(RefAgama::class, 'ref_agama_id');
    }

    public function statusKawin(): BelongsTo
    {
        return $this->belongsTo(RefStatusKawin::class, 'ref_status_kawin_id');
    }

    public function pekerjaan(): BelongsTo
    {
        return $this->belongsTo(RefPekerjaan::class, 'ref_pekerjaan_id');
    }

    public function pendidikan(): BelongsTo
    {
        return $this->belongsTo(RefPendidikan::class, 'ref_pendidikan_id');
    }

    public function kewarganegaraan(): BelongsTo
    {
        return $this->belongsTo(RefKewarganegaraan::class, 'ref_kewarganegaraan_id');
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh_user_id');
    }

    public function isMenunggu(): bool
    {
        return $this->status === self::STATUS_MENUNGGU;
    }

    public function isTerverifikasi(): bool
    {
        return $this->status === self::STATUS_TERVERIFIKASI;
    }

    public function isDitolak(): bool
    {
        return $this->status === self::STATUS_DITOLAK;
    }
}
