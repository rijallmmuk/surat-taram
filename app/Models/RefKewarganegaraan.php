<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Illuminate\Database\Eloquent\Model;

class RefKewarganegaraan extends Model
{
    use CatatAudit;

    public $timestamps = false;

    protected $table = 'ref_kewarganegaraan';

    protected $fillable = ['nama'];
}
