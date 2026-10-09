<?php

namespace App\Models;

use App\Models\Concerns\HapusBerkasPrivatLama;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nagari extends Model
{
    use HapusBerkasPrivatLama, HasFactory;

    protected $table = 'nagari';

    protected $fillable = [
        'nama_nagari',
        'nama_kecamatan',
        'nama_kabupaten',
        'nama_provinsi',
        'kode_wilayah',
        'kode_pos',
        'alamat_kantor',
        'telepon',
        'email',
        'website',
        'logo_path',
        'stempel_path',
        'mode_penomoran_default',
        'padding_digit_default',
    ];

    /**
     * @return list<string>
     */
    protected function kolomBerkasPrivat(): array
    {
        return ['stempel_path'];
    }

    public function setNamaKabupatenAttribute($value): void
    {
        $this->attributes['nama_kabupaten'] = trim(preg_replace('/^kabupaten\s+/i', '', (string) $value));
    }
}
