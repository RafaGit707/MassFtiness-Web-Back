<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservaClase extends Model
{
    use HasFactory;

    protected $table = 'reserva_clase';

    protected $fillable = [
        'clase_id',
        'dia_semana',
        'hora_inicio',
        'duracion_minutos',
        'capacidad_maxima',
    ];

    public function clase()
    {
        return $this->belongsTo(Clase::class);
    }
}
