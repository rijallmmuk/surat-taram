<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanSurat extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pengajuan_surat';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'jenis_surat_id',
        'penduduk_nik',
        'diajukan_oleh_user_id',
        'sumber',
        'data_isian',
        'konfigurasi_snapshot',
        'data_pemohon_snapshot',
        'status',
        'catatan_penolakan',
        'catatan_pengembalian',
        'diverifikasi_oleh_user_id',
        'diverifikasi_at',
        'diterbitkan_oleh_user_id',
        'pejabat_penandatangan_id',
        'diterbitkan_at',
        'nomor_surat_final',
        'nomor_urut_usulan',
        'nomor_surat_usulan',
        'kode_klasifikasi_snapshot',
        'kode_unit_snapshot',
        'nomor_urut_snapshot',
        'tanggal_surat',
        'file_pdf_path',
    ];

    /**
     * @var array<string, string>
     */
    public const LABEL_STATUS = [
        'diajukan' => 'Diajukan',
        'diverifikasi' => 'Diverifikasi',
        'diterbitkan' => 'Diterbitkan',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
    ];

    public static function labelStatus(string $status): string
    {
        return self::LABEL_STATUS[$status] ?? $status;
    }

    protected function casts(): array
    {
        return [
            'data_isian' => 'array',
            'konfigurasi_snapshot' => 'array',
            'data_pemohon_snapshot' => 'array',
            'diverifikasi_at' => 'datetime',
            'diterbitkan_at' => 'datetime',
            'tanggal_surat' => 'date',
            'nomor_urut_usulan' => 'integer',
            'nomor_urut_snapshot' => 'integer',
        ];
    }

    /**
     * Pengajuan milik warga: diajukan sendiri atau diinput petugas atas NIK-nya.
     */
    public function isMilik(User $user): bool
    {
        return $this->diajukan_oleh_user_id === $user->id
            || ($user->penduduk_nik !== null && $this->penduduk_nik === $user->penduduk_nik);
    }

    #[Scope]
    protected function milik(Builder $query, User $user): void
    {
        $query->where(function (Builder $milik) use ($user): void {
            $milik->where('diajukan_oleh_user_id', $user->id);

            if ($user->penduduk_nik !== null) {
                $milik->orWhere('penduduk_nik', $user->penduduk_nik);
            }
        });
    }

    /**
     * Data pemohon yang tercetak di surat: salinan saat verifikasi, atau data terkini bila belum diverifikasi.
     */
    public function pemohonSurat(): ?Penduduk
    {
        return $this->data_pemohon_snapshot !== null
            ? Penduduk::dariSnapshotSurat($this->data_pemohon_snapshot)
            : $this->penduduk;
    }

    public function jenisSurat(): BelongsTo
    {
        return $this->belongsTo(JenisSurat::class, 'jenis_surat_id');
    }

    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'penduduk_nik', 'nik');
    }

    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh_user_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh_user_id');
    }

    public function penerbit(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterbitkan_oleh_user_id');
    }

    public function pejabatPenandatangan(): BelongsTo
    {
        return $this->belongsTo(PejabatNagari::class, 'pejabat_penandatangan_id');
    }

    public function lampirans(): HasMany
    {
        return $this->hasMany(LampiranPengajuan::class, 'pengajuan_id');
    }
}
