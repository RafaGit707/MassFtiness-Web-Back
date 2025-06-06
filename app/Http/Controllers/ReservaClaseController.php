<?php

namespace App\Http\Controllers;

use App\Models\Clase;
use App\Models\Reserva;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Models\ReservaClase;
use Log;

class ReservaClaseController extends Controller
{
 public function index(Request $request, Clase $clase)
    {
        Log::info('ReservaClaseController@index para clase ID: ' . $clase->id);
        try {
            $horarios = $clase->horarios() // Usa la relación Clase->horarios()
                              ->orderBy('dia_semana')
                              ->orderBy('hora_inicio')
                              ->get();
            return response()->json($horarios);
        } catch (\Exception $e) {
            Log::error('Error en ReservaClaseController@index', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Error al obtener horarios definidos.'], 500);
        }
    }

    // Crea un nuevo horario definido (slot recurrente) PARA UNA CLASE ESPECÍFICA
    public function store(Request $request, Clase $clase)
    {
        Log::info('ReservaClaseController@store para clase ID: ' . $clase->id, ['data' => $request->all()]);
        $validatedData = $request->validate([
            'dia_semana' => 'required|integer|between:1,7',
            'hora_inicio' => 'required|date_format:H:i', // Frontend envía HH:MM
            'duracion_minutos' => 'required|integer|min:15',
            'capacidad_maxima' => 'required|integer|min:1',
        ]);

        // Convertir HH:MM a HH:MM:SS para la BD (tipo TIME)
        $validatedData['hora_inicio'] = Carbon::parse($validatedData['hora_inicio'])->format('H:i:s');
        $validatedData['clase_id'] = $clase->id;

        try {
            // Validar que no haya solapamientos para esta clase en este día y hora_inicio
            $existente = ReservaClase::where('clase_id', $clase->id)
                ->where('dia_semana', $validatedData['dia_semana'])
                ->where('hora_inicio', $validatedData['hora_inicio'])
                ->exists();

            if ($existente) {
                return response()->json(['message' => 'Ya existe un horario definido para esta clase en ese día y hora de inicio.'], 422);
            }

            $horarioDefinido = ReservaClase::create($validatedData);
            return response()->json($horarioDefinido, 201);
        } catch (\Exception $e) {
            Log::error('Error en ReservaClaseController@store', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Error al crear el horario definido.'], 500);
        }
    }

    public function show(ReservaClase $reservaClase) // $reservaClase es la instancia del slot por Route Model Binding
    {
        return $reservaClase;
    }

    public function update(Request $request, ReservaClase $reservaClase)
    {
        Log::info('ReservaClaseController@update para horario ID: ' . $reservaClase->id, ['data' => $request->all()]);
         $validatedData = $request->validate([
            'dia_semana' => 'sometimes|required|integer|between:1,7',
            'hora_inicio' => 'sometimes|required|date_format:H:i',
            'duracion_minutos' => 'sometimes|required|integer|min:15',
            'capacidad_maxima' => 'sometimes|required|integer|min:1',
        ]);

        if (isset($validatedData['hora_inicio'])) {
            $validatedData['hora_inicio'] = Carbon::parse($validatedData['hora_inicio'])->format('H:i:s');
        }

        // Aquí también deberías validar solapamientos si se cambia dia_semana u hora_inicio,
        // ignorando el $reservaClase->id actual.
        // Ej:
        // if (isset($validatedData['dia_semana']) || isset($validatedData['hora_inicio'])) {
        //     $dia = $validatedData['dia_semana'] ?? $reservaClase->dia_semana;
        //     $hora = isset($validatedData['hora_inicio']) ? $validatedData['hora_inicio'] : $reservaClase->hora_inicio;
        //     $existente = ReservaClase::where('clase_id', $reservaClase->clase_id)
        //         ->where('dia_semana', $dia)
        //         ->where('hora_inicio', $hora)
        //         ->where('id', '!=', $reservaClase->id) // Ignora el actual
        //         ->exists();
        //     if ($existente) {
        //         return response()->json(['message' => 'El cambio de horario crearía un solapamiento con otro horario existente.'], 422);
        //     }
        // }


        try {
            $reservaClase->update($validatedData);
            return response()->json($reservaClase);
        } catch (\Exception $e) {
            Log::error('Error en ReservaClaseController@update', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Error al actualizar el horario definido.'], 500);
        }
    }

    public function destroy(Request $request, ReservaClase $reservaClase) // $reservaClase es el slot de horario
    {
        Log::info('ReservaClaseController@destroy para horario definido ID: ' . $reservaClase->id);
        try {
            // Contar reservas hechas para este slot específico en CUALQUIER fecha
            // Esto asume que la tabla 'reservas' tiene una FK 'reserva_clase_id'
            $reservasHechasParaEsteSlot = Reserva::where('reserva_clase_id', $reservaClase->id)->count();
            $forceDelete = $request->input('force', false);

            if ($reservasHechasParaEsteSlot > 0 && !$forceDelete) {
                return response()->json([
                    'message' => 'No se puede eliminar este horario directamente.',
                    'detailed_message' => "Este horario tiene {$reservasHechasParaEsteSlot} reserva(s) de usuarios. ¿Estás seguro de que quieres eliminar este horario y todas sus reservas asociadas?",
                    'requires_force' => true
                ], 422);
            }

            DB::beginTransaction();
            if ($forceDelete && $reservasHechasParaEsteSlot > 0) {
                Reserva::where('reserva_clase_id', $reservaClase->id)->delete();
                Log::info("Reservas para el horario definido ID {$reservaClase->id} eliminadas.");
            }

            $deleted = $reservaClase->delete();
            DB::commit();

            if ($deleted) {
                return response()->noContent();
            } else {
                return response()->json(['message' => 'El horario definido no pudo ser eliminado.'], 500);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ReservaClaseController@destroy', ['id' => $reservaClase->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Error al eliminar el horario definido.'], 500);
        }
    }

}
