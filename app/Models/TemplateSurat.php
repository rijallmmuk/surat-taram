<?php

namespace App\Models;

use App\Services\TemplatSurat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateSurat extends Model
{
    use HasFactory;

    protected $table = 'template_surat';

    protected $fillable = [
        'jenis_surat_id',
        'versi',
        'konten',
        'status_aktif',
        'dibuat_oleh_user_id',
    ];

    protected function casts(): array
    {
        return [
            'versi' => 'integer',
            'konten' => 'array',
            'status_aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Salinan pratinjau blok dibuat ulang editor saat dimuat, sehingga tidak perlu disimpan.
        static::saving(function (TemplateSurat $template): void {
            if (is_array($template->konten)) {
                $template->konten = TemplatSurat::bersihkan($template->konten);
            }
        });
    }

    public function jenisSurat(): BelongsTo
    {
        return $this->belongsTo(JenisSurat::class, 'jenis_surat_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_user_id');
    }
}
