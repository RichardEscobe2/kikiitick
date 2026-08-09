<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $fillable = [
        'usuario_id',
        'vendido_por_usuario_id',
        'taquilla_id',
        'vendedor_id',
        'monto_neto',
        'total_comisiones',
        'monto_total',
        'estatus_pago',
        'metodo_pago',
        'fecha_venta',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Personal de taquilla que procesó la venta (null en compras en línea).
     */
    public function vendidoPor()
    {
        return $this->belongsTo(User::class, 'vendido_por_usuario_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'venta_id');
    }

    public function accesos()
    {
        return $this->hasMany(Acceso::class, 'venta_id');
    }

    public function taquilla()
    {
        return $this->belongsTo(Taquilla::class, 'taquilla_id');
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }
}