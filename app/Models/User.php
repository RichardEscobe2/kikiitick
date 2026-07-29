<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Teatro;

class User extends Authenticatable
{
    use SoftDeletes, HasFactory, Notifiable, HasApiTokens;

    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'usuarios';

    /**
     * 🛡️ PROTECCIÓN DE ASIGNACIÓN MASIVA ($fillable)
     */
    protected $fillable = [
        'nombre',
        'correo',
        'contrasena',
        'rol',
        'estatus_organizador',
        'codigo_verificacion',
        'codigo_expira_en',
        'correo_verificado_at',
    ];

    /**
     * 🔒 PROTECCIÓN DE CONFIDENCIALIDAD ($hidden)
     */
    protected $hidden = [
        'contrasena',
        'codigo_verificacion',
        'remember_token',
    ];

    /**
     * Desactivar timestamps por defecto
     */
    public $timestamps = false;

    /**
     * Indica a Laravel cuál es la columna usada para la contraseña.
     */
    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    /**
     * Conversión automática de tipos de datos.
     */
    protected function casts(): array
    {
        return [
            'correo_verificado_at' => 'datetime',
            'contrasena'           => 'hashed',
        ];
    }

    /**
     * Relación: Un usuario organizador puede tener uno o varios teatros/recintos
     */
    public function teatros()
    {
        return $this->hasMany(Teatro::class, 'usuario_id');
    }
}