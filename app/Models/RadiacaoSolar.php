<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadiacaoSolar extends Model
{
    protected $table = 'radiacao_solar';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $fillable = [
        'distrito_id',
        'timestamp',
        'radiacao_w_m2'
    ];

    protected $casts = [
        'timestamp'=>'datetime',
        'radiacao_w_m2'=>'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
