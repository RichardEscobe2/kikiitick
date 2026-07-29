<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoletoEvento extends Model
{
    use HasFactory;

    protected $table = 'boletos_evento';

    protected $fillable = [
        'evento_id',
        'zona_teatro_id',
        'precio_base',
        'stock_disponible',
    ];

    protected $casts = [
        'precio_base'      => 'decimal:2',
        'stock_disponible' => 'integer',
    ];

    /**
     * Relación: Un boleto/tarifa pertenece a un Evento
     */
    public function evento()
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    /**
     * Relación: Un boleto/tarifa pertenece a una Zona del Teatro
     */
    public function zonaTeatro()
    {
        return $this->belongsTo(ZonaTeatro::class, 'zona_teatro_id');
    }
}