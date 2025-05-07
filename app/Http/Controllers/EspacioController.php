<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Espacio;

class EspacioController extends Controller
{
    public function index()
    {
        return Espacio::all();
    }

    public function store(Request $request)
    {
        return Espacio::create($request->all());
    }

    public function show(Espacio $espacio)
    {
        return $espacio;
    }

    public function update(Request $request, Espacio $espacio)
    {
        $espacio->update($request->all());
        return $espacio;
    }

    public function destroy(Espacio $espacio)
    {
        $espacio->delete();
        return response()->noContent();
    }
}
