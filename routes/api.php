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
Route::post('/logout', [UsuarioController::class, 'logout']);

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

    // Rutas adicionales que requieran autenticación
});