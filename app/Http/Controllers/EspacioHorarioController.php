<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\Reserva;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Models\EspacioHorario;
use Log;

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

    public function getHorariosDisponiblesEspacio(Request $request, Espacio $espacio)
{
    $request->validate(['fecha' => 'required|date_format:Y-m-d']);
    $fechaSeleccionada = Carbon::parse($request->input('fecha'))->startOfDay();

    Log::info('getHorariosDisponiblesEspacio para espacio ID: ' . $espacio->id . ' en fecha: ' . $fechaSeleccionada->toDateString());

    $horariosParaFrontend = [];
    // Define el rango de horas de operación, ej. de 8 AM a 10 PM
    $horaInicioGym = 8;
    $horaFinGym = 22;
    $duracionSlotMinutos = 60; // Reservas de 1 hora

    for ($hora = $horaInicioGym; $hora < $horaFinGym; $hora++) {
        $fechaHoraInicioSlot = $fechaSeleccionada->copy()->hour($hora)->minute(0)->second(0);
        $fechaHoraFinSlot = $fechaHoraInicioSlot->copy()->addMinutes($duracionSlotMinutos);

        // Verifica si ya existe una reserva para este espacio en este slot de tiempo
        // Esto es simplificado, necesitarías chequear solapamientos si las duraciones varían
        $reservaExistente = Reserva::where('espacio_id', $espacio->id)
                                ->where('horario_reserva', $fechaHoraInicioSlot->toDateTimeString())
                                ->exists();

        // Aquí no hay una "capacidad_maxima" por slot como en las clases,
        // se asume que un espacio solo puede tener una reserva a la vez.
        // Si un espacio puede tener múltiples reservas simultáneas (raro), necesitas capacidad.
        $disponible = !$reservaExistente;

        $horariosParaFrontend[] = [
            // Para espacios, el 'id' podría no ser de un slot predefinido,
            // sino la propia hora o una combinación.
            // Usaremos la hora_inicio como identificador temporal para el frontend.
            'id' => $hora, // O un ID más robusto si es necesario
            'hora_inicio' => $fechaHoraInicioSlot->format('H:i'),
            'hora_fin' => $fechaHoraFinSlot->format('H:i'),
            'disponible' => $disponible,
        ];
    }
    return response()->json($horariosParaFrontend);
}

public function crearReservaEspacio(Request $request)
{
    $validatedData = $request->validate([
        'espacio_id' => 'required|integer|exists:espacios,id',
        // El frontend debe enviar la fecha y la hora de inicio deseadas
        'fecha_reserva' => 'required|date_format:Y-m-d',
        'hora_inicio_reserva' => 'required|date_format:H:i', // Ej: "09:00"
    ]);

    $usuario = $request->user();
    $espacio = Espacio::findOrFail($validatedData['espacio_id']);
    $fechaHoraInicioReserva = Carbon::parse($validatedData['fecha_reserva'] . ' ' . $validatedData['hora_inicio_reserva']);

    // --- VALIDACIONES ADICIONALES CRÍTICAS ---
    DB::beginTransaction();
    try {
        // Volver a verificar disponibilidad (para concurrencia)
        $reservaExistente = Reserva::where('espacio_id', $espacio->id)
                                ->where('horario_reserva', $fechaHoraInicioReserva->toDateTimeString())
                                ->lockForUpdate() // Bloquea para evitar doble reserva
                                ->exists();

        if ($reservaExistente) {
            DB::rollBack();
            return response()->json(['message' => 'Lo sentimos, este espacio ya está reservado para la hora seleccionada.'], 422);
        }

        // Verificar si el usuario ya tiene una reserva para este mismo slot de espacio
        $reservaPreviaUsuario = Reserva::where('usuario_id', $usuario->id)
                                ->where('espacio_id', $espacio->id)
                                ->where('horario_reserva', $fechaHoraInicioReserva->toDateTimeString())
                                ->exists();
        if ($reservaPreviaUsuario) {
             DB::rollBack();
            return response()->json(['message' => 'Ya tienes una reserva para este espacio en este horario.'], 422);
        }


        $reserva = Reserva::create([
            'usuario_id' => $usuario->id,
            'espacio_id' => $espacio->id,
            'tipo_reserva' => 'espacio',
            'horario_reserva' => $fechaHoraInicioReserva->toDateTimeString(),
            'estado' => 'confirmada',
        ]);

        DB::commit();
        Log::info('Reserva de espacio CREADA:', ['reserva_id' => $reserva->id, 'user_id' => $usuario->id]);
        return response()->json($reserva->load(['espacio', 'usuario']), 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al crear reserva de espacio:', ['message' => $e->getMessage()]);
        return response()->json(['message' => 'No se pudo completar la reserva en este momento.'], 500);
    }
}
}
