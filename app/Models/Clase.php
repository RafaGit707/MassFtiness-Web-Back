<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clase extends Model
{
    use HasFactory;

    protected $table = 'clases';

    protected $fillable = [
        'nombre',
        'capacidad_maxima',
        'entrenador_id',
    ];

    public function entrenador()
    {
        return $this->belongsTo(Entrenador::class);
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    public function horarios()
    {
        return $this->hasMany(ReservaClase::class);
    }
}
