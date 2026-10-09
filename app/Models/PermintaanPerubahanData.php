<?php

namespace App\Models;

use App\Services\PerubahanDataPendudukService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PermintaanPerubahanData extends Model
{
    protected $table = 'permintaan_perubahan_data';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'penduduk_nik',
        'diajukan_oleh_user_id',
        'data_lama',
        'data_baru',
        'alasan',
        'status',
        'catatan_sekretaris',
        'diproses_oleh_user_id',
        'diproses_at',
    ];

    protected function casts(): array
    {
        return [
            'data_lama' => 'array',
            'data_baru' => 'array',
            'diproses_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $permintaan): void {
            $permintaan->id ??= (string) Str::uuid();
        });
    }

    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'penduduk_nik', 'nik');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh_user_id');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh_user_id');
    }

    /** @return array<int, array{label: string, lama: string, baru: string}> */
    public function rincian(): array
    {
        $rincian = [];

        foreach ($this->data_baru ?? [] as $field => $baru) {
            $rincian[] = [
                'label' => PerubahanDataPendudukService::FIELDS[$field] ?? $field,
                'lama' => $this->formatNilai($field, $this->data_lama[$field] ?? null),
                'baru' => $this->formatNilai($field, $baru),
            ];
        }

        return $rincian;
    }

    private function formatNilai(string $field, mixed $value): string
    {
        if (blank($value)) {
            return 'Belum terisi';
        }

        return match ($field) {
            'jenis_kelamin' => $value === 'L' ? 'Laki-laki' : 'Perempuan',
            'tanggal_lahir' => Carbon::parse($value)->format('d/m/Y'),
            'jorong_id' => Jorong::query()->whereKey($value)->value('nama_jorong') ?? (string) $value,
            'ref_agama_id' => RefAgama::query()->whereKey($value)->value('nama') ?? (string) $value,
            'ref_status_kawin_id' => RefStatusKawin::query()->whereKey($value)->value('nama') ?? (string) $value,
            'ref_pekerjaan_id' => RefPekerjaan::query()->whereKey($value)->value('nama') ?? (string) $value,
            'ref_pendidikan_id' => RefPendidikan::query()->whereKey($value)->value('nama') ?? (string) $value,
            'ref_kewarganegaraan_id' => RefKewarganegaraan::query()->whereKey($value)->value('nama') ?? (string) $value,
            default => (string) $value,
        };
    }
}
