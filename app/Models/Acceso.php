<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acceso extends Model
{
    protected $table = 'accesos';

    protected $fillable = [
        'venta_id',
        'boleto_evento_id',
        'tipo_boleto',
        'clave_evento',
        'numero_control',
        'hash_seguridad',
        'token_qr',
        'seccion_pasillo',
        'fila_palco',
        'numero_asiento',
        'estatus',
        'escaneado_at',
    ];

    protected $casts = [
        'escaneado_at' => 'datetime',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function boletoEvento()
    {
        return $this->belongsTo(BoletoEvento::class);
    }
}
