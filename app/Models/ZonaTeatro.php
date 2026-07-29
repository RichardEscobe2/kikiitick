<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZonaTeatro extends Model
{
    use HasFactory;

    protected $table = 'zonas_teatro';

    protected $fillable = [
        'teatro_id',
        'nombre_zona',
        'nivel_proximidad',
        'capacidad_asientos',
        'fila_inicio',
        'fila_fin',
        'es_numerada',
    ];

    protected $casts = [
        'es_numerada' => 'boolean',
    ];

    public function teatro()
    {
        return $this->belongsTo(Teatro::class, 'teatro_id');
    }

    public function asientos()
    {
        return $this->hasMany(Asiento::class, 'zona_teatro_id');
    }
}