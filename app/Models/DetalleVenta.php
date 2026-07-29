<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    use HasFactory;

    protected $table = 'detalles_venta';

    protected $fillable = [
        'venta_id',
        'boleto_evento_id',
        'cantidad',
        'subtotal',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function boletoEvento()
    {
        return $this->belongsTo(BoletoEvento::class, 'boleto_evento_id');
    }
}