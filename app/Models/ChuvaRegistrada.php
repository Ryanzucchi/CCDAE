<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChuvaRegistrada extends Model
{
    protected $table = 'chuva_registrada';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $fillable = [
        'distrito_id',
        'timestamp',
        'precipitacao_mm'
    ];

    protected $casts = [
        'timestamp'=>'datetime',
        'precipitacao_mm'=>'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
