<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;

class Penduduk extends Model
{
    use CatatAudit, HasFactory;

    protected $table = 'penduduk';

    protected $primaryKey = 'nik';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nik',
        'kk_number',
        'jorong_id',
        'nama',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'ref_agama_id',
        'ref_status_kawin_id',
        'ref_pekerjaan_id',
        'ref_pendidikan_id',
        'ref_kewarganegaraan_id',
        'no_hp',
        'status_penduduk',
    ];

    /**
     * @var array<string, string>
     */
    public const LABEL_STATUS = [
        'aktif' => 'Aktif',
        'meninggal' => 'Meninggal',
        'pindah' => 'Pindah',
    ];

    /**
     * Teks identitas tidak boleh diawali =, +, -, @ (dibaca sebagai formula di spreadsheet)
     * atau memuat < dan > (tag HTML yang ikut tercetak di surat).
     */
    public const POLA_TEKS_TIDAK_AMAN = '/^[=+\-@]|[<>]/';

    public const PESAN_TEKS_TIDAK_AMAN = 'tidak boleh diawali tanda =, +, -, @ atau memuat tanda < dan >.';

    protected function casts(): array
    {
        return [
            'nik' => 'string',
            'kk_number' => 'string',
            'tanggal_lahir' => 'date',
        ];
    }

    public function setNikAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['nik'] = null;

            return;
        }

        $str = (string) $value;
        $this->attributes['nik'] = is_numeric($str) && str_contains($str, 'E')
            ? sprintf('%.0f', (float) $str)
            : preg_replace('/\D/', '', $str);
    }

    public function setKkNumberAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['kk_number'] = null;

            return;
        }

        $str = (string) $value;
        $this->attributes['kk_number'] = is_numeric($str) && str_contains($str, 'E')
            ? sprintf('%.0f', (float) $str)
            : preg_replace('/\D/', '', $str);
    }

    public function getAlamatAttribute(): string
    {
        if (! $this->jorong) {
            return 'Nagari Taram';
        }

        $namaJorong = $this->jorong->nama_jorong;
        $prefix = str_starts_with(strtolower($namaJorong), 'jorong') ? '' : 'Jorong ';

        return "{$prefix}{$namaJorong}, Nagari Taram";
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

    /**
     * Relasi yang ikut tercetak pada surat.
     */
    public const RELASI_SURAT = ['jorong', 'agama', 'statusKawin', 'pekerjaan', 'pendidikan', 'kewarganegaraan'];

    /**
     * Salinan data pemohon untuk dibekukan pada pengajuan saat verifikasi.
     *
     * @return array{atribut: array<string, mixed>, relasi: array<string, array<string, mixed>|null>}
     */
    public function snapshotSurat(): array
    {
        $this->loadMissing(self::RELASI_SURAT);

        return [
            'atribut' => $this->getAttributes(),
            'relasi' => collect(self::RELASI_SURAT)
                ->mapWithKeys(fn (string $relasi): array => [$relasi => $this->getRelation($relasi)?->getAttributes()])
                ->all(),
        ];
    }

    /**
     * Penduduk tiruan (tidak tersimpan) dari salinan {@see snapshotSurat()}.
     *
     * @param  array{atribut: array<string, mixed>, relasi: array<string, array<string, mixed>|null>}  $snapshot
     */
    public static function dariSnapshotSurat(array $snapshot): self
    {
        $penduduk = (new self)->setRawAttributes($snapshot['atribut']);

        foreach (self::RELASI_SURAT as $relasi) {
            $atribut = $snapshot['relasi'][$relasi] ?? null;
            $penduduk->setRelation($relasi, $atribut === null
                ? null
                : $penduduk->{$relasi}()->getRelated()->newInstance()->setRawAttributes($atribut));
        }

        return $penduduk;
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'penduduk_nik', 'nik');
    }

    public function pengajuanSurat(): HasMany
    {
        return $this->hasMany(PengajuanSurat::class, 'penduduk_nik', 'nik');
    }

    public function dokumenWargas(): HasMany
    {
        return $this->hasMany(DokumenWarga::class, 'penduduk_nik', 'nik');
    }

    protected static function booted(): void
    {
        static::updated(function (Penduduk $penduduk): void {
            if ($penduduk->wasChanged('nik')) {
                $newNik = $penduduk->nik;

                // Sinkronkan username akun login warga bila memakai NIK lama
                User::where('role', 'warga')
                    ->where('penduduk_nik', $newNik)
                    ->update(['username' => $newNik]);
            }

            if ($penduduk->wasChanged('tanggal_lahir') && $penduduk->tanggal_lahir) {
                $user = User::where('role', 'warga')
                    ->where('penduduk_nik', $penduduk->nik)
                    ->first();

                if ($user && $user->password_changed_at === null) {
                    $birthDate = Carbon::parse($penduduk->tanggal_lahir)->format('dmY');
                    $user->update(['password' => Hash::make($birthDate)]);
                }
            }

            if ($penduduk->wasChanged('nama')) {
                User::where('role', 'warga')
                    ->where('penduduk_nik', $penduduk->nik)
                    ->update(['name' => $penduduk->nama]);
            }
        });

        static::deleting(function (Penduduk $penduduk): void {
            User::where('role', 'warga')
                ->where(function ($query) use ($penduduk): void {
                    $query->where('penduduk_nik', $penduduk->nik)
                        ->orWhere('username', $penduduk->nik);
                })
                ->delete();
        });
    }
}
