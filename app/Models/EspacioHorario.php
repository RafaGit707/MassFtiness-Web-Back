<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EspacioHorario extends Model
{
    use HasFactory;

    protected $table = 'espacio_horario';

    protected $fillable = [
        'espacio_id',
        'horario_reserva',
        'capacidad_actual',
        'capacidad_maxima',
    ];

    public function espacio()
    {
        return $this->belongsTo(Espacio::class);
    }
}
