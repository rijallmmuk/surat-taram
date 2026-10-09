<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Illuminate\Database\Eloquent\Model;

class RefPendidikan extends Model
{
    use CatatAudit;

    public $timestamps = false;

    protected $table = 'ref_pendidikan';

    protected $fillable = ['nama'];
}
