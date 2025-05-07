<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'usuarios';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'nombre_usuario',
        'correo_electronico',
        'contrasena',
        'rol',
    ];

    protected $hidden = [
        'contrasena',
        'remember_token',
    ];

    public function getAuthPassword() {
        return $this->contrasena;
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }
}
