<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudOrganizador extends Model
{
    protected $table = 'solicitudes_organizador';

    protected $fillable = [
        'usuario_id',
        'recinto_nombre',
        'recinto_direccion',
        'recinto_capacidad',
        'telefono_contacto',
        'rfc',
        'descripcion',
    ];

    protected $casts = [
        'recinto_capacidad' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
