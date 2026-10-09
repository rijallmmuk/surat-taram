<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Illuminate\Database\Eloquent\Model;

class RefStatusKawin extends Model
{
    use CatatAudit;

    public $timestamps = false;

    protected $table = 'ref_status_kawin';

    protected $fillable = ['nama'];
}
