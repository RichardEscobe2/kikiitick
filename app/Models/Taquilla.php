<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Taquilla extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'taquillas';

    protected $fillable = [
        'teatro_id',
        'nombre',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function teatro()
    {
        return $this->belongsTo(Teatro::class, 'teatro_id');
    }

    public function cajeros()
    {
        return $this->hasMany(User::class, 'taquilla_id');
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'taquilla_id');
    }
}
