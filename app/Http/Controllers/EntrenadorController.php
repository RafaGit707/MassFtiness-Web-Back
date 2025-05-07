<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Entrenador;

class EntrenadorController extends Controller
{
    public function index()
    {
        return Entrenador::all();
    }

    public function store(Request $request)
    {
        return Entrenador::create($request->all());
    }

    public function show(Entrenador $entrenador)
    {
        return $entrenador;
    }

    public function update(Request $request, Entrenador $entrenador)
    {
        $entrenador->update($request->all());
        return $entrenador;
    }

    public function destroy(Entrenador $entrenador)
    {
        $entrenador->delete();
        return response()->noContent();
    }
}
