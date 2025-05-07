<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EspacioHorario;

class EspacioHorarioController extends Controller
{
    public function index()
    {
        return EspacioHorario::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'espacio_id' => 'required|exists:espacios,id',
            'horario_reserva' => 'required|date_format:H:i',
            'capacidad_actual' => 'required|integer|min:0',
            'capacidad_maxima' => 'required|integer|min:1',
        ]);

        return EspacioHorario::create($request->all());
    }

    public function show(EspacioHorario $espacioHorario)
    {
        return $espacioHorario;
    }

    public function update(Request $request, EspacioHorario $espacioHorario)
    {
        $espacioHorario->update($request->all());
        return $espacioHorario;
    }

    public function destroy(EspacioHorario $espacioHorario)
    {
        $espacioHorario->delete();
        return response()->noContent();
    }
}
