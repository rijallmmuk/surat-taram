<?php

namespace App\Models;

use App\Services\KodeIsian;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkemaFormKolomTabel extends Model
{
    public $timestamps = false;

    protected $table = 'skema_form_kolom_tabel';

    protected $fillable = [
        'skema_form_field_id',
        'nama_kolom',
        'label',
        'tipe_kolom',
        'format_isian',
        'referensi_master',
        'opsi_pilihan',
        'wajib',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'opsi_pilihan' => 'array',
            'wajib' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SkemaFormKolomTabel $kolom) {
            if (blank($kolom->nama_kolom)) {
                $kolom->nama_kolom = KodeIsian::buat($kolom->label, $kolom->skema_form_field_id
                    ? static::where('skema_form_field_id', $kolom->skema_form_field_id)->pluck('nama_kolom')
                    : [], 'kolom');
            }

            if ($kolom->skema_form_field_id && $kolom->isDirty('nama_kolom')) {
                $originalSlug = $kolom->nama_kolom;
                $counter = 1;
                while (static::where('skema_form_field_id', $kolom->skema_form_field_id)
                    ->where('nama_kolom', $kolom->nama_kolom)
                    ->when($kolom->id, fn ($q) => $q->where('id', '!=', $kolom->id))
                    ->exists()
                ) {
                    $counter++;
                    $kolom->nama_kolom = "{$originalSlug}_{$counter}";
                }
            }
        });
    }

    public function skemaFormField(): BelongsTo
    {
        return $this->belongsTo(SkemaFormField::class, 'skema_form_field_id');
    }
}
