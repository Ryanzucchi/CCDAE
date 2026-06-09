<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RadiacaoSolar extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()->logAll(); // Registra todas as mudanças
    }
    protected $table = 'radiacao_solar';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    public function getKey()
    {
        $timestamp = $this->timestamp;
        if ($timestamp instanceof \DateTimeInterface) {
            $timestamp = $timestamp->format('Y-m-d_H:i:s');
        }
        return "{$this->distrito_id}_{$timestamp}";
    }
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
