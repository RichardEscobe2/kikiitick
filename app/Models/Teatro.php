<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teatro extends Model
{
    use HasFactory;

    protected $table = 'teatros';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'nombre',
        'ubicacion',
        'capacidad_total',
        'filas_totales',
        'asientos_por_fila',
        'pasillos_slots',
        'posicion_escenario',
    ];

    protected $casts = [
        'pasillos_slots' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function zonas()
    {
        return $this->hasMany(ZonaTeatro::class, 'teatro_id');
    }

    public function asientos()
    {
        return $this->hasMany(Asiento::class, 'teatro_id');
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class, 'teatro_id');
    }
}