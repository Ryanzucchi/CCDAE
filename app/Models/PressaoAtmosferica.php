<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PressaoAtmosferica extends Model
{
    protected $table = 'pressao_atmosferica';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $fillable = [
        'distrito_id',
        'timestamp',
        'pressao_hpa'
    ];

    protected $casts = [
        'timestamp'=>'datetime',
        'pressao_hpa'=>'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
