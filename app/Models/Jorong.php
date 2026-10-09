<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jorong extends Model
{
    use CatatAudit, HasFactory;

    protected $table = 'jorongs';

    protected $fillable = [
        'nama_jorong',
    ];

    public function penduduk(): HasMany
    {
        return $this->hasMany(Penduduk::class, 'jorong_id');
    }

    public function getNamaLengkapAttribute(): string
    {
        return str_starts_with(strtolower($this->nama_jorong), 'jorong')
            ? $this->nama_jorong
            : "Jorong {$this->nama_jorong}";
    }
}
