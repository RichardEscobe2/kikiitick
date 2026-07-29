<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsientoEvento extends Model
{
    use HasFactory;

    protected $table = 'asientos_evento';

    protected $fillable = [
        'evento_id',
        'asiento_id',
        'estado',
        'reservado_por_usuario_id',
        'reservado_hasta',
    ];

    protected $casts = [
        'reservado_hasta' => 'datetime',
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function asiento()
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }
}