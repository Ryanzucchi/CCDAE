<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ParticulaAr extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()->logAll(); // Registra todas as mudanças
    }
    protected $table = 'particulas_ar';

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
        'pm25',
        'pm10',
        'poeira',
        'areia',
        'poluentes'
    ];

    protected $casts = [
        'timestamp'=>'datetime',
        'pm25'=>'float',
        'pm10'=>'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
