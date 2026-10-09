<?php

namespace App\Models;

use App\Services\KodeIsian;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SkemaFormField extends Model
{
    use HasFactory;

    protected $table = 'skema_form_fields';

    protected $fillable = [
        'jenis_surat_id',
        'parent_group',
        'nama_field',
        'label',
        'tipe_field',
        'format_isian',
        'referensi_master',
        'opsi_pilihan',
        'kondisi_tipe',
        'kondisi_kunci',
        'kondisi_nilai',
        'wajib',
        'is_optional_group',
        'hanya_pemeriksaan',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
            'is_optional_group' => 'boolean',
            'hanya_pemeriksaan' => 'boolean',
            'urutan' => 'integer',
            'opsi_pilihan' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SkemaFormField $field) {
            if (blank($field->nama_field)) {
                $field->nama_field = KodeIsian::buat($field->label, $field->jenis_surat_id
                    ? static::where('jenis_surat_id', $field->jenis_surat_id)->pluck('nama_field')
                    : []);
            }

            if (filled($field->parent_group)) {
                $field->parent_group = Str::snake(preg_replace('/[^a-zA-Z0-9_\s]/', '', $field->parent_group));
            }

            if (empty($field->parent_group)) {
                $field->is_optional_group = false;
            }

            if ($field->jenis_surat_id && $field->isDirty('nama_field')) {
                $originalSlug = $field->nama_field;
                $counter = 1;
                while (static::where('jenis_surat_id', $field->jenis_surat_id)
                    ->where('nama_field', $field->nama_field)
                    ->when($field->id, fn ($q) => $q->where('id', '!=', $field->id))
                    ->exists()
                ) {
                    $counter++;
                    $field->nama_field = "{$originalSlug}_{$counter}";
                }
            }
        });

        static::saved(function () {
            Cache::forget('builder_parent_groups_db');
        });

        static::deleted(function () {
            Cache::forget('builder_parent_groups_db');
        });
    }

    public function jenisSurat(): BelongsTo
    {
        return $this->belongsTo(JenisSurat::class, 'jenis_surat_id');
    }

    public function kolomTabels(): HasMany
    {
        return $this->hasMany(SkemaFormKolomTabel::class, 'skema_form_field_id')->orderBy('urutan');
    }
}
