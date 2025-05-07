<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reserva;

class ReservaController extends Controller
{
    public function index()
    {
        return Reserva::all();
    }

    public function store(Request $request)
    {
        return Reserva::create($request->all());
    }

    public function show(Reserva $reserva)
    {
        return $reserva;
    }

    public function update(Request $request, Reserva $reserva)
    {
        $reserva->update($request->all());
        return $reserva;
    }

    public function destroy(Reserva $reserva)
    {
        $reserva->delete();
        return response()->noContent();
    }
}
