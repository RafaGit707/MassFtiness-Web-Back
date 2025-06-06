<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Entrenador;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EntrenadorController extends Controller
{
      public function index(Request $request)
    {
        Log::info('EntrenadorController@index', ['user_id' => optional($request->user())->id]);
        try {
            return Entrenador::all();
        } catch (\Exception $e) {
            Log::error('Error en EntrenadorController@index', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Error interno al obtener entrenadores'], 500);
        }
    }

    public function store(Request $request)
    {
        Log::info('EntrenadorController@store', ['data' => $request->all(), 'user_id' => optional($request->user())->id]);
        $validatedData = $request->validate([
            'nombre_entrenador' => 'required|string|max:255|unique:entrenadores,nombre_entrenador',
            'especializacion' => 'required|string|max:255',
        ]);

        try {
            $entrenador = Entrenador::create($validatedData);
            return response()->json($entrenador, 201);
        } catch (\Exception $e) {
            Log::error('Error en EntrenadorController@store', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Error interno al crear entrenador'], 500);
        }
    }

    public function show(Request $request, $entrenadorId) // Cambiado para usar el ID directamente
    {
        Log::info('EntrenadorController@show - Solicitud para ID (desde URL): ' . $entrenadorId, ['user_id' => optional($request->user())->id]);
        $entrenador = Entrenador::find($entrenadorId); // Carga manual

        if (!$entrenador) {
            Log::error('EntrenadorController@show - Entrenador no encontrado con ID: ' . $entrenadorId);
            return response()->json(['message' => 'Entrenador no encontrado.'], 404);
        }

        Log::info('EntrenadorController@show - Entrenador encontrado:', $entrenador->toArray()); // Loguea el entrenador encontrado
        return response()->json($entrenador);
    }

    public function update(Request $request, $entrenadorId) // Cambia Entrenador $entrenador a $entrenadorId
    {
        Log::info('EntrenadorController@update - Solicitud recibida para ID (desde URL): ' . $entrenadorId, ['data' => $request->all()]);

        $entrenador = Entrenador::find($entrenadorId); // Intenta encontrar el entrenador manualmente

        if (!$entrenador) {
            Log::error('EntrenadorController@update - Entrenador no encontrado con ID: ' . $entrenadorId);
            return response()->json(['message' => 'Entrenador no encontrado.'], 404);
        }
        Log::info('EntrenadorController@update - Entrenador encontrado manualmente ID: ' . $entrenador->id);


        // --- VALIDACIÓN ---
        $validatedData = $request->validate([
            'nombre_entrenador' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('entrenadores', 'nombre_entrenador')->ignore($entrenador->id)
            ],
            'especializacion' => 'sometimes|required|string|max:255',
        ]);
        Log::info('EntrenadorController@update - Datos validados para ID: ' . $entrenador->id, $validatedData);

        try {
            $result = $entrenador->update($validatedData);
            if ($result) {
                Log::info('EntrenadorController@update - ACTUALIZACIÓN EXITOSA para ID: ' . $entrenador->id);
            } else {
                Log::warning('EntrenadorController@update - $entrenador->update() devolvió false para ID: ' . $entrenador->id . '. Los datos podrían no haber cambiado.');
                 // Si update devuelve false porque no hay cambios, aún así es un "éxito" funcional.
                 // Devolver el modelo fresco es buena idea.
            }
            return response()->json($entrenador->fresh());
        } catch (\Exception $e) {
            Log::error('Error en EntrenadorController@update para ID: ' . $entrenador->id, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error interno al actualizar entrenador: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $entrenadorId) // Cambia Entrenador $entrenador a $entrenadorId
    {
        Log::info('EntrenadorController@destroy - Solicitud para ID (desde URL): ' . $entrenadorId, ['requesting_user_id' => optional($request->user())->id]);

        $entrenador = Entrenador::find($entrenadorId); // Intenta encontrar el entrenador manualmente

        if (!$entrenador) {
            Log::error('EntrenadorController@destroy - Entrenador no encontrado con ID: ' . $entrenadorId);
            return response()->json(['message' => 'Entrenador no encontrado.'], 404);
        }
        Log::info('EntrenadorController@destroy - Entrenador encontrado manualmente ID: ' . $entrenador->id);

        try {
            // Verificar dependencias
            if ($entrenador->clases()->exists()) {
                Log::warning('EntrenadorController@destroy - Dependencias: Clases asignadas.', ['id' => $entrenador->id]);
                return response()->json(['message' => 'No se puede eliminar el entrenador: tiene clases asignadas.'], 422);
            }
            if ($entrenador->espacios()->exists()) { // Asume que tienes esta relación
                Log::warning('EntrenadorController@destroy - Dependencias: Espacios asignados.', ['id' => $entrenador->id]);
                return response()->json(['message' => 'No se puede eliminar el entrenador: tiene espacios asignados.'], 422);
            }

            $deleted = $entrenador->delete();

            if ($deleted) {
                Log::info('EntrenadorController@destroy - ELIMINACIÓN EXITOSA para ID: ' . $entrenador->id);
                return response()->noContent();
            } else {
                Log::warning('EntrenadorController@destroy - delete() devolvió false para ID: ' . $entrenador->id);
                return response()->json(['message' => 'El entrenador no pudo ser eliminado (delete devolvió false).'], 500);
            }
        } catch (\Illuminate\Database\QueryException $qe) {
            Log::error('Error de BD en EntrenadorController@destroy', ['id' => $entrenador->id, 'error_code' => $qe->getCode(), 'error' => $qe->getMessage()]);
            if ($qe->getCode() === '23000' || (isset($qe->errorInfo[1]) && $qe->errorInfo[1] == 1451)) {
                return response()->json(['message' => 'No se puede eliminar el entrenador porque está asociado a otros registros.'], 422);
            }
            return response()->json(['message' => 'Error de base de datos al eliminar.'], 500);
        } catch (\Exception $e) {
            Log::error('Error general en EntrenadorController@destroy', ['id' => $entrenador->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Error interno al eliminar.'], 500);
        }
    }

}
