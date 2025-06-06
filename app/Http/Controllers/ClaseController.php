<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use App\Models\Clase;
use Log;

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

    public function destroy(Request $request, Clase $clase)
    {
        Log::info('ClaseController@destroy para ID: ' . $clase->id);
        try {
            $reservasAsociadasCount = $clase->reservas()->count(); // Asume relación 'reservas' en Clase
            $horariosDefinidosCount = $clase->horarios()->count(); // Asume relación 'horarios' en Clase

            // Parámetro para forzar la eliminación
            $forceDelete = $request->input('force', false);

            if (($reservasAsociadasCount > 0 || $horariosDefinidosCount > 0) && !$forceDelete) {
                $messages = [];
                if ($reservasAsociadasCount > 0) $messages[] = "Tiene {$reservasAsociadasCount} reserva(s) de usuarios asociadas.";
                if ($horariosDefinidosCount > 0) $messages[] = "Tiene {$horariosDefinidosCount} horario(s) definidos asociados.";

                return response()->json([
                    'message' => 'No se puede eliminar la clase directamente debido a dependencias.',
                    'detailed_message' => implode(' ', $messages) . ' ¿Estás seguro de que quieres eliminar esta clase y todas sus dependencias (horarios y reservas)?',
                    'requires_force' => true // Indicador para el frontend
                ], 422); // Unprocessable Content, indica que se requiere acción adicional
            }

            // Si se fuerza o no hay dependencias
            DB::beginTransaction();
            // Si se fuerza la eliminación, primero elimina las dependencias
            if ($forceDelete) {
                if ($reservasAsociadasCount > 0) {
                    $clase->reservas()->delete(); // Elimina las reservas asociadas
                    Log::info("Reservas asociadas a la clase ID {$clase->id} eliminadas.");
                }
                if ($horariosDefinidosCount > 0) {
                    // Si los horarios definidos (ReservaClase) también tienen reservas,
                    // necesitarías una lógica en cascada aquí también o la BD lo manejaría.
                    // Por ahora, borramos los horarios definidos de la clase:
                    $clase->horarios()->delete();
                    Log::info("Horarios definidos asociados a la clase ID {$clase->id} eliminados.");
                }
            }

            $deleted = $clase->delete();
            DB::commit();

            if ($deleted) {
                Log::info('Clase eliminada ID: ' . $clase->id);
                return response()->noContent();
            } else {
                Log::warning('delete() devolvió false para clase ID: ' . $clase->id);
                return response()->json(['message' => 'La clase no pudo ser eliminada.'], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ClaseController@destroy', ['id' => $clase->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Error interno al eliminar la clase.'], 500);
        }
    }
}
