<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\EntrenadorController;
use App\Http\Controllers\EspacioController;
use App\Http\Controllers\ClaseController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\EspacioHorarioController;
use App\Http\Controllers\ReservaClaseController;

Route::post('/registrar', [UsuarioController::class, 'registrar']);
Route::post('/login', [UsuarioController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('usuarios', UsuarioController::class)->except(['store']);
    Route::put('/usuarios/{usuario}/cambiar-contrasena', [UsuarioController::class, 'cambiarContrasena']);
    Route::get('/usuarios/{id}/reservas', [UsuarioController::class, 'reservas']);
    Route::get('/usuarioAutenticado', [UsuarioController::class, 'usuarioAutenticado']);
    Route::post('/logout', [UsuarioController::class, 'logout']);
    Route::get('/usuarios/{id}/reservas', [UsuarioController::class, 'reservas']);

    Route::apiResource('usuarios', UsuarioController::class);
    Route::apiResource('entrenadores', EntrenadorController::class);
    Route::apiResource('espacios', EspacioController::class);
    Route::apiResource('clases', ClaseController::class);
    Route::apiResource('reservas', ReservaController::class);
    Route::apiResource('espacio-horarios', EspacioHorarioController::class);
    Route::apiResource('reserva-clases', ReservaClaseController::class);

     // --- NUEVAS RUTAS PARA HORARIOS Y RESERVAS ---
    // Obtener horarios disponibles para una clase específica en una fecha
    Route::get('reservas/clase/{clase}/horarios-disponibles', [ReservaController::class, 'getHorariosDisponiblesClase'])
        ->name('reservas.clase.horarios');


    // Obtener horarios disponibles para un espacio específico en una fecha
    Route::get('reservas/espacio/{espacio}/horarios-disponibles', [ReservaController::class, 'getHorariosDisponiblesEspacio'])
        ->name('reservas.espacio.horarios');

    // Crear una reserva para una clase
    Route::post('reservas/clase', [ReservaController::class, 'crearReservaClase']);

    // Crear una reserva para un espacio
    Route::post('reservas/espacio', [ReservaController::class, 'crearReservaEspacio']);

    Route::apiResource('clases.horariosDefinidos', ReservaClaseController::class)->shallow()->parameters([
        'horariosDefinidos' => 'reservaClase' // Para las rutas superficiales, usa 'reservaClase' como nombre de parámetro
    ]);

    Route::get('mis-reservas', [ReservaController::class, 'misReservas'])->name('api.reservas.mis-reservas');
    Route::delete('reservas/{reserva}/cancelar', [ReservaController::class, 'cancelarReserva'])->name('api.reservas.cancelar');
});