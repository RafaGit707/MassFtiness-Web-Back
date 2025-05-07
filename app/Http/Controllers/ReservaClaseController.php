<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReservaClase;

class ReservaClaseController extends Controller
{
    public function index()
    {
        return ReservaClase::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'clase_id' => 'required|exists:clases,id',
            'horario_reserva' => 'required|date_format:H:i',
            'capacidad_actual' => 'required|integer|min:0',
            'capacidad_maxima' => 'required|integer|min:1',
        ]);

        return ReservaClase::create($request->all());
    }

    public function show(ReservaClase $reservaClase)
    {
        return $reservaClase;
    }

    public function update(Request $request, ReservaClase $reservaClase)
    {
        $reservaClase->update($request->all());
        return $reservaClase;
    }

    public function destroy(ReservaClase $reservaClase)
    {
        $reservaClase->delete();
        return response()->noContent();
    }
}
