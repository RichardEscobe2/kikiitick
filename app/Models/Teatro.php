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

    /**
     * 🛡️ Root cause de Módulo 4 (pasillos "invisibles" en el plano interactivo):
     * sin este orderBy, MySQL no garantiza devolver los asientos en el orden de
     * inserción (slot_index 1..N) — en la práctica los devolvía en un orden
     * interno arbitrario, así que los slots de pasillo aparecían agrupados al
     * inicio de cada fila en vez de intercalados en su posición real entre
     * bloques de butacas. EventoController::getMapaEvento() (y cualquier otro
     * consumidor de esta relación) dependen de este orden para que el frontend
     * pueda recorrer la fila secuencialmente y dibujar el hueco donde de verdad
     * va.
     */
    public function asientos()
    {
        return $this->hasMany(Asiento::class, 'teatro_id')->orderBy('fila')->orderBy('slot_index');
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class, 'teatro_id');
    }
}