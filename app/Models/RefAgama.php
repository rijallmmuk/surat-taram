<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Illuminate\Database\Eloquent\Model;

class RefAgama extends Model
{
    use CatatAudit;

    public $timestamps = false;

    protected $table = 'ref_agama';

    protected $fillable = ['nama'];
}
