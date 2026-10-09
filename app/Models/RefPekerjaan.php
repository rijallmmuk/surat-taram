<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Illuminate\Database\Eloquent\Model;

class RefPekerjaan extends Model
{
    use CatatAudit;

    public $timestamps = false;

    protected $table = 'ref_pekerjaan';

    protected $fillable = ['id', 'nama'];
}
