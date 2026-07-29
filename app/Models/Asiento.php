<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asiento extends Model
{
    use HasFactory;

    protected $table = 'asientos';

    protected $fillable = [
        'teatro_id',
        'zona_teatro_id',
        'fila',
        'numero',
        'codigo',
        'slot_index',
        'tipo',
    ];

    public function teatro()
    {
        return $this->belongsTo(Teatro::class, 'teatro_id');
    }

    public function zonaTeatro()
    {
        return $this->belongsTo(ZonaTeatro::class, 'zona_teatro_id');
    }

    public function estadosEvento()
    {
        return $this->hasMany(AsientoEvento::class, 'asiento_id');
    }
}