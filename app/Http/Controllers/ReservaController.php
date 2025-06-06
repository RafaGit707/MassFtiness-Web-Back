<?php

namespace App\Http\Controllers;

use App\Models\Clase;
use App\Models\Espacio;
use App\Models\ReservaClase;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Models\Reserva;
use Log;

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

     public function getHorariosDisponiblesClase(Request $request, Clase $clase)
    {
        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
        ]);

        $fechaSeleccionada = Carbon::parse($request->input('fecha'))->startOfDay();
        $diaDeLaSemana = $fechaSeleccionada->dayOfWeekIso; // 1 (Lunes) a 7 (Domingo)

        Log::info('ReservaController@getHorariosDisponiblesClase para clase ID: ' . $clase->id .
                  ' en fecha: ' . $fechaSeleccionada->toDateString() .
                  ' (Día de semana ISO: ' . $diaDeLaSemana . ')');

        try {
            // 1. Obtener los slots de horario DEFINIDOS para esta clase en el día de la semana correspondiente
            //    Asumimos que tu modelo Clase tiene una relación horarios() a ReservaClase.
            $slotsDefinidos = $clase->horarios() // Esta relación debe apuntar a tu tabla de horarios definidos
                                    ->where('dia_semana', $diaDeLaSemana)
                                    // ->where('activo', true) // Si tienes un campo 'activo'
                                    ->orderBy('hora_inicio')
                                    ->get();

            if ($slotsDefinidos->isEmpty()) {
                Log::info('No hay horarios definidos para la clase ID: ' . $clase->id . ' el día ' . $diaDeLaSemana . ' (según la fecha ' . $fechaSeleccionada->toDateString() . ')');
                return response()->json([]);
            }

            $horariosParaFrontend = [];

            foreach ($slotsDefinidos as $slot) {
                // $slot es una instancia de ReservaClase (o como llames a tu modelo de horario definido)

                // 2. Construye el DATETIME completo para este slot en la fecha seleccionada
                $fechaHoraInicioSlot = $fechaSeleccionada->copy()->setTimeFromTimeString($slot->hora_inicio);

                // 3. Contar cuántas reservas YA existen para esta clase en este DATETIME específico
                $reservasExistentes = Reserva::where('clase_id', $clase->id)
                                            ->where('horario_reserva', $fechaHoraInicioSlot->toDateTimeString())
                                            ->count();

                $cuposDisponibles = $slot->capacidad_maxima - $reservasExistentes;

                $horariosParaFrontend[] = [
                    'id_slot_definido' => $slot->id, // ID del registro en reserva_clase/clase_horarios_definidos
                    'fecha_con_hora_inicio' => $fechaHoraInicioSlot->toIso8601String(), // Para que el frontend sepa el datetime exacto
                    'hora_inicio' => Carbon::parse($slot->hora_inicio)->format('H:i'), // Formato HH:MM
                    'hora_fin' => Carbon::parse($slot->hora_inicio)->addMinutes($slot->duracion_minutos ?? 60)->format('H:i'),
                    'disponible' => $cuposDisponibles > 0,
                    'capacidad_restante' => $cuposDisponibles,
                    'capacidad_maxima_slot' => $slot->capacidad_maxima
                ];
            }

            return response()->json($horariosParaFrontend);

        } catch (\Exception $e) {
            Log::error('Error en ReservaController@getHorariosDisponiblesClase', [
                'clase_id' => $clase->id, 'fecha' => $fechaSeleccionada->toDateString(),
                'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error al obtener horarios disponibles.'], 500);
        }
    }

    public function crearReservaClase(Request $request)
    {
        Log::info('ReservaController@crearReservaClase - Datos recibidos:', $request->all());

        $validatedData = $request->validate([
            'clase_id' => 'required|integer|exists:clases,id',
            'horario_clase_id' => 'required|integer|exists:reserva_clase,id',
             'fecha_hora_inicio_utc' => [ 'required','date',
                function ($attribute, $value, $fail) {
                    $fechaReservaAppTZ = Carbon::parse($value)->setTimezone(config('app.timezone'));
                    $ahoraAppTZ = Carbon::now(config('app.timezone'));
                    if ($fechaReservaAppTZ->isPast()) { // O $fechaReservaAppTZ->lt($ahoraAppTZ) para ser estricto
                        $fail('La ' . $attribute . ' (' . $fechaReservaAppTZ->toDateTimeString() . ') no puede ser una fecha u hora pasada (Ahora: '.$ahoraAppTZ->toDateTimeString().').');
                    }
                },
            ],
        ]);
        Log::info('ReservaController@crearReservaClase - Datos validados:', $validatedData);

        $usuario = $request->user();
        $clase = Clase::findOrFail($validatedData['clase_id']);
        $slotDefinido = ReservaClase::findOrFail($validatedData['horario_clase_id']);

        $fechaHoraReservaEnAppTZ = Carbon::parse($validatedData['fecha_hora_inicio_utc'])
                                        ->setTimezone(config('app.timezone'));

        Log::info('--- DEBUG DE HORAS (App Timezone: ' . config('app.timezone') . ') ---');
        Log::info('Slot Definido (BD)      => ID: ' . $slotDefinido->id . ', Dia: ' . $slotDefinido->dia_semana . ', Hora Inicio (TIME): ' . $slotDefinido->hora_inicio);
        Log::info('Reserva Solicitada (UTC) => ' . $validatedData['fecha_hora_inicio_utc']);
        Log::info('Reserva Solicitada (AppTZ) => ' . $fechaHoraReservaEnAppTZ->toDateTimeString());
        Log::info('  -> Hora de Slot Definido (parseada como TIME - H:i:s): ' . Carbon::parse($slotDefinido->hora_inicio)->format('H:i:s'));


        // --- VALIDACIONES DE NEGOCIO ---
        if ($slotDefinido->clase_id != $clase->id) {
            Log::warning('Conflicto: El slot definido no pertenece a la clase solicitada.');
            return response()->json(['message' => 'El horario seleccionado no es válido para esta clase.'], 422);
        }

        if ($fechaHoraReservaEnAppTZ->dayOfWeekIso != $slotDefinido->dia_semana) {
            Log::warning('Conflicto: El día de la semana no coincide.', ['reserva_dia' => $fechaHoraReservaEnAppTZ->dayOfWeekIso, 'slot_dia' => $slotDefinido->dia_semana]);
            return response()->json(['message' => 'La fecha de la reserva no corresponde al día programado para este horario.'], 422);
        }

        $horaReservaString = $fechaHoraReservaEnAppTZ->format('H:i:s');
        $horaInicioSlotDefinidoString = Carbon::parse($slotDefinido->hora_inicio)->format('H:i:s');

        if ($horaReservaString != $horaInicioSlotDefinidoString) {
            Log::warning('Conflicto: La hora no coincide.', ['reserva_hora' => $horaReservaString, 'slot_hora' => $horaInicioSlotDefinidoString]);
            return response()->json(['message' => 'La hora de la reserva no coincide con la hora programada para este horario.'], 422);
        }

        // --- LÓGICA DE CONCURRENCIA Y CAPACIDAD ---
        DB::beginTransaction();
        try {
            $slotDefinidoLocked = ReservaClase::where('id', $slotDefinido->id)->lockForUpdate()->first();
            if (!$slotDefinidoLocked) {
                DB::rollBack();
                Log::warning('Conflicto de concurrencia: Slot no disponible o modificado.', ['slot_id' => $slotDefinido->id]);
                return response()->json(['message' => 'El horario seleccionado ya no está disponible. Por favor, inténtalo de nuevo.'], 409);
            }

            // Contar reservas existentes para la clase en el datetime exacto.
            // NO filtramos por 'estado' si la columna ya no existe.
            $reservasExistentes = Reserva::where('clase_id', $clase->id)
                                        ->where('horario_reserva', $fechaHoraReservaEnAppTZ->toDateTimeString())
                                        ->count();
            Log::info('Capacidad Check: Slot CapMax=' . $slotDefinidoLocked->capacidad_maxima . ', ReservasExistentes=' . $reservasExistentes);

            if (($slotDefinidoLocked->capacidad_maxima - $reservasExistentes) <= 0) {
                DB::rollBack();
                Log::warning('Clase llena.', ['clase_id' => $clase->id, 'horario' => $fechaHoraReservaEnAppTZ->toDateTimeString()]);
                return response()->json(['message' => 'Lo sentimos, esta clase ya está llena para este horario y fecha.'], 422);
            }

            // Verificar si el usuario ya tiene una reserva para esta clase en este horario y fecha.
            // NO filtramos por 'estado' si la columna ya no existe.
            // Verificar si el usuario ya tiene una reserva para esta clase en este horario y fecha.
            $reservaPrevia = Reserva::where('usuario_id', $usuario->id) // Del usuario autenticado
                                    ->where('clase_id', $clase->id)     // Para la misma clase
                                    ->where('horario_reserva', $fechaHoraReservaEnAppTZ->toDateTimeString()) // En el mismo DATETIME exacto
                                    // No necesitas filtrar por 'estado' aquí si una reserva (incluso pendiente si tuvieras ese estado)
                                    // ya cuenta como una reserva para ese slot. Si solo las 'confirmada' cuentan, añade el filtro.
                                    ->exists();

            if ($reservaPrevia) {
                DB::rollBack(); // Importante si estás dentro de una transacción
                Log::warning('Usuario ya tiene reserva para esta clase/horario.', [
                    'user_id' => $usuario->id,
                    'clase_id' => $clase->id,
                    'horario' => $fechaHoraReservaEnAppTZ->toDateTimeString()
                ]);
                return response()->json(['message' => 'Ya tienes una reserva para esta clase en este horario y fecha.'], 422); // 422 Unprocessable Content es apropiado
            }

            $reserva = Reserva::create([
                'usuario_id' => $usuario->id,
                'clase_id' => $clase->id,
                'tipo_reserva' => 'clase',
                'horario_reserva' => $fechaHoraReservaEnAppTZ->toDateTimeString(),
                'reserva_clase_id' => $slotDefinidoLocked->id,
            ]);

            DB::commit();
            Log::info('Reserva de clase CREADA:', ['reserva_id' => $reserva->id, 'usuario_id' => $usuario->id]);
            return response()->json($reserva->load(['clase', 'usuario']), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error EXCEPCIÓN al crear reserva de clase:', [
                'user_id' => $usuario->id,
                'clase_id' => $validatedData['clase_id'],
                'horario_clase_id' => $validatedData['horario_clase_id'],
                'fecha_hora_utc' => $validatedData['fecha_hora_inicio_utc'],
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'No se pudo completar la reserva en este momento. Inténtalo más tarde.'], 500);
        }
    }

    public function getHorariosDisponiblesEspacio(Request $request, Espacio $espacio)
    {
        $request->validate(['fecha' => 'required|date_format:Y-m-d']);
        $fechaSeleccionada = Carbon::parse($request->input('fecha'))->startOfDay();
        $usuarioAutenticado = $request->user();

        Log::info('ReservaController@getHorariosDisponiblesEspacio para espacio ID: ' . $espacio->id .
                ' en fecha: ' . $fechaSeleccionada->toDateString() .
                ' por usuario ID: ' . ($usuarioAutenticado ? $usuarioAutenticado->id : 'N/A'));

        try {
            $horaApertura = 8;
            $horaCierre = 22;
            $duracionSlotMinutos = 60;
            $MAX_RESERVAS_ESPACIO_POR_DIA_USUARIO = 3; // Límite por usuario

            $horariosParaFrontend = [];
            $ahoraEnAppTZ = Carbon::now(config('app.timezone'));

            // Obtener todas las reservas del usuario actual para este espacio en este día
            // para no contarlas dos veces contra su límite si ya tiene alguna
            $idsReservasDelUsuarioHoyParaEsteEspacio = [];
            if($usuarioAutenticado){
                $idsReservasDelUsuarioHoyParaEsteEspacio = Reserva::where('usuario_id', $usuarioAutenticado->id)
                    ->where('espacio_id', $espacio->id) // Importante filtrar por el espacio actual también
                    ->where('tipo_reserva', 'espacio')
                    ->whereDate('horario_reserva', $fechaSeleccionada->toDateString())
                    ->pluck('horario_reserva') // Obtener los datetimes de sus reservas
                    ->map(function ($datetime) {
                        return Carbon::parse($datetime)->format('H:i'); // Mapear a "HH:MM" para fácil comparación
                    })->toArray();
            }
            $numReservasPreviasUsuarioHoy = count($idsReservasDelUsuarioHoyParaEsteEspacio);


            for ($hora = $horaApertura; $hora < $horaCierre; $hora++) {
                $fechaHoraInicioSlotEnAppTZ = $fechaSeleccionada->copy()->hour($hora)->minute(0)->second(0);

                if ($fechaSeleccionada->isToday() && $fechaHoraInicioSlotEnAppTZ->isBefore($ahoraEnAppTZ)) {
                    continue;
                }

                // Contar cuántas reservas YA existen para este espacio en este slot exacto
                $reservasExistentesCount = Reserva::where('espacio_id', $espacio->id)
                                            ->where('horario_reserva', $fechaHoraInicioSlotEnAppTZ->toDateTimeString())
                                            ->count();

                $cuposRestantesEnSlot = $espacio->capacidad_maxima - $reservasExistentesCount;

                $usuarioYaReservoEsteSlotEspecifico = in_array($fechaHoraInicioSlotEnAppTZ->format('H:i'), $idsReservasDelUsuarioHoyParaEsteEspacio);

                // Disponible para el usuario si:
                // 1. Hay cupos en el slot O el usuario ya tiene este slot (para permitir deselección en UI)
                // Y
                // 2. El usuario no ha alcanzado su límite de NUEVAS reservas para el día
                $puedeSeleccionar = false;
                if ($cuposRestantesEnSlot > 0) { // Hay cupo general en el slot
                    if ($usuarioYaReservoEsteSlotEspecifico) { // Si ya lo tiene, puede deseleccionarlo
                        $puedeSeleccionar = true;
                    } elseif ($numReservasPreviasUsuarioHoy < $MAX_RESERVAS_ESPACIO_POR_DIA_USUARIO) { // Si no lo tiene y aún tiene "crédito" de reservas diarias
                        $puedeSeleccionar = true;
                    }
                } elseif ($usuarioYaReservoEsteSlotEspecifico) { // No hay cupo general, pero es Suyo
                    $puedeSeleccionar = true; // Para permitir deselección
                }

                $horariosParaFrontend[] = [
                    'id_slot_generado' => $fechaHoraInicioSlotEnAppTZ->format('H:i'), // "08:00", "09:00", etc.
                    'fecha_con_hora_inicio' => $fechaHoraInicioSlotEnAppTZ->toIso8601String(),
                    'hora_inicio' => $fechaHoraInicioSlotEnAppTZ->format('H:i'),
                    'hora_fin' => $fechaHoraInicioSlotEnAppTZ->copy()->addMinutes($duracionSlotMinutos)->format('H:i'),
                    'disponible' => $puedeSeleccionar,
                    'ocupado_por_otro' => ($cuposRestantesEnSlot <= 0 && !$usuarioYaReservoEsteSlotEspecifico),
                    'capacidad_maxima_slot' => $espacio->capacidad_maxima,
                    'reservas_hechas' => $reservasExistentesCount,
                    'capacidad_restante' => $cuposRestantesEnSlot > 0 ? $cuposRestantesEnSlot : 0,
                    'usuario_tiene_reserva_aqui' => $usuarioYaReservoEsteSlotEspecifico,
                    'usuario_reservas_previas' => $numReservasPreviasUsuarioHoy
                ];
            }
            return response()->json($horariosParaFrontend);

        } catch (\Exception $e) { /* ... manejo de error ... */ }
    }

    public function crearReservaEspacio(Request $request)
    {
        Log::info('ReservaController@crearReservaEspacio - Datos recibidos:', $request->all());
            $validatedData = $request->validate([
                'espacio_id' => 'required|integer|exists:espacios,id',
                'fecha_hora_inicio_utc' => 'required|date', // Frontend envía el datetime UTC exacto del slot seleccionado
            ]);
        Log::info('ReservaController@crearReservaEspacio - Datos validados:', $validatedData);

        $usuario = $request->user();
        $espacio = Espacio::findOrFail($validatedData['espacio_id']);
        $fechaHoraReservaEnAppTZ = Carbon::parse($validatedData['fecha_hora_inicio_utc'])
                                    ->setTimezone(config('app.timezone'));

        // Convertir el datetime UTC enviado a la zona horaria de la aplicación
        $fechaHoraReservaEnAppTZ = Carbon::parse($validatedData['fecha_hora_inicio_utc'])
                                        ->setTimezone(config('app.timezone'));

        $MAX_RESERVAS_ESPACIO_POR_DIA_USUARIO = 3;
        $reservasDelUsuarioHoy = Reserva::where('usuario_id', $usuario->id)
                                        ->where('tipo_reserva', 'espacio')
                                        ->whereDate('horario_reserva', $fechaHoraReservaEnAppTZ->toDateString())
                                        ->count();
        if ($reservasDelUsuarioHoy >= $MAX_RESERVAS_ESPACIO_POR_DIA_USUARIO) {
            // Considera si esta verificación debería ser más inteligente:
            // si está intentando reservar un slot que YA tiene, no debería contar para el límite
            // pero si es un NUEVO slot, sí.
            // La lógica actual previene más de 3 reservas totales para el día.
            return response()->json(['message' => "Has alcanzado el límite de {$MAX_RESERVAS_ESPACIO_POR_DIA_USUARIO} reservas de espacio para este día."], 422);
        }


        DB::beginTransaction();
        try {
            // Contar reservas existentes para ESTE espacio en ESTE slot
            $reservasExistentesParaSlot = Reserva::where('espacio_id', $espacio->id)
                                        ->where('horario_reserva', $fechaHoraReservaEnAppTZ->toDateTimeString())
                                        ->lockForUpdate() // Importante para concurrencia
                                        ->count();

            if ($reservasExistentesParaSlot >= $espacio->capacidad_maxima) { // Compara con la capacidad del ESPACIO
                DB::rollBack();
                return response()->json(['message' => 'Lo sentimos, este espacio ya ha alcanzado su capacidad máxima para la hora seleccionada.'], 422);
            }

            // Verificar si el usuario ya tiene una reserva para ESTE espacio en ESTE horario
            $reservaPreviaUsuario = Reserva::where('usuario_id', $usuario->id)
                                        ->where('espacio_id', $espacio->id)
                                        ->where('horario_reserva', $fechaHoraReservaEnAppTZ->toDateTimeString())
                                        ->exists();
            if ($reservaPreviaUsuario) {
                DB::rollBack();
                return response()->json(['message' => 'Ya tienes una reserva para este espacio en este horario y fecha.'], 422);
            }

            $reserva = Reserva::create([
                'usuario_id' => $usuario->id,
                'espacio_id' => $espacio->id,
                'tipo_reserva' => 'espacio',
                'horario_reserva' => $fechaHoraReservaEnAppTZ->toDateTimeString(),
            ]);

            DB::commit();
            return response()->json($reserva->load(['espacio', 'usuario']), 201);

        } catch (\Exception $e) { /* ... rollback y error 500 ... */ }
    }

     public function misReservas(Request $request)
    {
        $usuario = $request->user();
        Log::info('ReservaController@misReservas para usuario ID: ' . $usuario->id);

        try {
            // Obtener reservas futuras (o todas si prefieres y filtras en frontend)
            // Cargar relaciones para mostrar nombres de clase/espacio
            $reservas = Reserva::where('usuario_id', $usuario->id)
                              // ->where('horario_reserva', '>=', Carbon::now(config('app.timezone'))) // Solo futuras
                              ->with(['clase' => function ($query) {
                                  $query->select('id', 'nombre'); // Solo campos necesarios de clase
                              }, 'espacio' => function ($query) {
                                  $query->select('id', 'nombre'); // Solo campos necesarios de espacio
                              }])
                              ->orderBy('horario_reserva', 'asc') // Más próximas primero
                              ->get();

            // Podrías transformar la data aquí si es necesario para el frontend
            // Por ejemplo, para que 'horario_reserva' ya esté en un formato amigable
            // o para añadir una descripción combinada.

            return response()->json($reservas);

        } catch (\Exception $e) {
            Log::error('Error en ReservaController@misReservas', [
                'user_id' => $usuario->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error al obtener tus reservas.'], 500);
        }
    }

    // (Opcional) Endpoint para cancelar una reserva
    public function cancelarReserva(Request $request, Reserva $reserva) // Route Model Binding para Reserva
    {
        $usuario = $request->user();

        // Verificar que la reserva pertenezca al usuario autenticado
        if ($reserva->usuario_id !== $usuario->id) {
            return response()->json(['message' => 'No tienes permiso para cancelar esta reserva.'], 403);
        }

        // Verificar si la reserva aún se puede cancelar (ej. no en el pasado o muy cerca del evento)
        $horarioReserva = Carbon::parse($reserva->horario_reserva)->setTimezone(config('app.timezone'));
        if ($horarioReserva->isPast() || $horarioReserva->diffInHours(Carbon::now(config('app.timezone'))) < 1) { // Ejemplo: no cancelar con menos de 1h de antelación
             return response()->json(['message' => 'Esta reserva ya no puede ser cancelada.'], 422);
        }

        try {
            // Opción 1: Eliminar la reserva
            // $reserva->delete();
            // Log::info("Reserva ID {$reserva->id} eliminada por usuario ID {$usuario->id}");

            // Opción 2: Marcar como cancelada (si tienes un campo 'estado')
            // $reserva->update(['estado' => 'cancelada']);
            // Log::info("Reserva ID {$reserva->id} marcada como cancelada por usuario ID {$usuario->id}");

            // Si usas un campo 'estado', elige la Opción 2. Si no, la Opción 1.
            // Por ahora, asumiremos eliminación directa.
            $reserva->delete();
            Log::info("Reserva ID {$reserva->id} cancelada/eliminada por usuario ID {$usuario->id}");

            // IMPORTANTE: Si tenías un contador de capacidad_actual en ReservaClase o EspacioHorario,
            // deberías decrementarlo aquí (dentro de una transacción).
            // Pero como estamos contando al vuelo, no es estrictamente necesario.

            return response()->json(['message' => 'Reserva cancelada correctamente.']);

        } catch (\Exception $e) {
            Log::error('Error en ReservaController@cancelarReserva', ['reserva_id' => $reserva->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Error al cancelar la reserva.'], 500);
        }
    }
    
}
