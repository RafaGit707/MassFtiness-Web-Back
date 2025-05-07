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
        'horario_reserva',
        'capacidad_actual',
        'capacidad_maxima',
    ];

    public function clase()
    {
        return $this->belongsTo(Clase::class);
    }
}
