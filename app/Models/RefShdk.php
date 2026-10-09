<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Illuminate\Database\Eloquent\Model;

class RefShdk extends Model
{
    use CatatAudit;

    public $timestamps = false;

    protected $table = 'ref_shdk';

    protected $fillable = ['nama'];
}
