<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Antena extends Model
{
    use LogsActivity;

    protected $table = 'antenas';
    protected $guarded = [];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'data_instalacao' => 'date',
        'ultima_manutencao' => 'date'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
