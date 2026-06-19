<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TemperaturaRegistrada extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()->logAll(); // Registra todas as mudanças
    }
    protected $table = 'temperatura_registrada';

    public $timestamps = false;

    // IMPORTANTE
    public $incrementing = false;
    protected $primaryKey = 'filament_id';

    public function getKey()
    {
        $timestamp = $this->timestamp;
        if ($timestamp instanceof \DateTimeInterface) {
            $timestamp = $timestamp->format('Y-m-d_H:i:s');
        }
        return "{$this->distrito_id}_{$timestamp}";
    }

    public function getFilamentIdAttribute()
    {
        return $this->getKey();
    }

    protected $fillable = [
        'distrito_id',
        'timestamp',
        'temperatura',
        'sensacao_termica'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'temperatura' => 'float',
        'sensacao_termica' => 'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
