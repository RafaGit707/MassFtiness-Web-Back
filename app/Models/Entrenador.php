<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrenador extends Model
{
    use HasFactory;

    protected $table = 'entrenadores';

    protected $fillable = [
        'nombre_entrenador',
        'especializacion',
    ];

    public function espacios()
    {
        return $this->hasMany(Espacio::class);
    }

    public function clases()
    {
        return $this->hasMany(Clase::class);
    }
}
