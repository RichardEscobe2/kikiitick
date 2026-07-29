<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;

    protected $table = 'eventos';

    protected $fillable = [
        'teatro_id',
        'titulo',
        'descripcion',
        'imagen_url',
        'categoria',
        'fecha_hora',
        'comision_fija_empresa',
        'estatus',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'comision_fija_empresa' => 'decimal:2',
    ];

    /**
     * Relación: Un evento pertenece a un Teatro/Recinto
     */
    public function teatro()
    {
        return $this->belongsTo(Teatro::class, 'teatro_id');
    }

    /**
     * Relación: Un evento tiene configuradas varias tarifas/boletos por zona (NUEVA)
     */
    public function boletosEvento()
    {
        return $this->hasMany(BoletoEvento::class, 'evento_id');
    }

    public function asientosEvento()
    {
        return $this->hasMany(AsientoEvento::class, 'evento_id');
    }
}
