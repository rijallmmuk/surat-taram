<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MasterSyaratDokumen extends Model
{
    use CatatAudit, HasFactory;

    protected $table = 'master_syarat_dokumen';

    protected $fillable = [
        'nama_dokumen',
        'slug',
        'keterangan_default',
        'wajib_default',
    ];

    protected function casts(): array
    {
        return [
            'wajib_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MasterSyaratDokumen $model) {
            if (! $model->exists && filled($model->nama_dokumen)) {
                $baseSlug = substr(Str::slug($model->nama_dokumen, '_'), 0, 140) ?: 'dokumen';
                $slug = $baseSlug;
                $suffix = 2;

                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}_{$suffix}";
                    $suffix++;
                }

                $model->slug = $slug;
            }
        });
    }

    public function syaratDokumens(): HasMany
    {
        return $this->hasMany(SyaratDokumen::class, 'master_syarat_dokumen_id');
    }

    public function dokumenWargas(): HasMany
    {
        return $this->hasMany(DokumenWarga::class, 'master_syarat_dokumen_id');
    }
}
