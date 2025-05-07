<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Clase;

class ClaseController extends Controller
{
    public function index()
    {
        return Clase::all();
    }

    public function store(Request $request)
    {
        return Clase::create($request->all());
    }

    public function show(Clase $clase)
    {
        return $clase;
    }

    public function update(Request $request, Clase $clase)
    {
        $clase->update($request->all());
        return $clase;
    }

    public function destroy(Clase $clase)
    {
        $clase->delete();
        return response()->noContent();
    }
}
