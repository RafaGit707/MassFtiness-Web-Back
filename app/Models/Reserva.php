<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'usuario_id',
        'espacio_id',
        'clase_id',
        'tipo_reserva',
        'horario_reserva',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function espacio()
    {
        return $this->belongsTo(Espacio::class);
    }

    public function clase()
    {
        return $this->belongsTo(Clase::class);
    }
}
