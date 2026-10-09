<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Illuminate\Database\Eloquent\Model;

class RefSuku extends Model
{
    use CatatAudit;

    public $timestamps = false;

    protected $table = 'ref_suku';

    protected $fillable = ['nama'];
}
