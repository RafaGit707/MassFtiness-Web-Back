<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function registrar(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'nombre_usuario' => 'required|string|unique:usuarios',
            'correo_electronico' => 'required|email|unique:usuarios',
            'contrasena' => 'required|min:6',
        ]);

        $usuario = Usuario::create([
            'nombre' => $request->nombre,
            'nombre_usuario' => $request->nombre_usuario,
            'correo_electronico' => $request->correo_electronico,
            'contrasena' => Hash::make($request->contrasena),
        ]);

        $token = $usuario->createToken('token')->plainTextToken;

        return response()->json(['token' => $token, 'usuario' => $usuario]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'nombre_usuario' => 'required',
            'contrasena' => 'required',
        ]);

        $usuario = Usuario::where('nombre_usuario', $request->nombre_usuario)->first();

        if (! $usuario || ! Hash::check($request->contrasena, $usuario->contrasena)) {
            throw ValidationException::withMessages([
                'nombre_usuario' => ['Credenciales incorrectas.'],
            ]);
        }

        $token = $usuario->createToken('token')->plainTextToken;

        return response()->json(['token' => $token, 'usuario' => $usuario]);
    }

    public function usuarioAutenticado(Request $request)
    {
        return $request->user();
    }
    
    public function index()
    {
        return Usuario::all();
    }

    public function store(Request $request)
    {
        return Usuario::create($request->all());
    }

    public function show(Usuario $usuario)
    {
        return $usuario;
    }

    // public function update(Request $request, Usuario $usuario)
    // {
    //     $usuario->update($request->all());
    //     return $usuario;
    // }

    public function update(Request $request, Usuario $usuario)
    {
        // --- VALIDACIÓN ---
        $validatedData = $request->validate([
            'nombre' => 'sometimes|required|string|max:255', // sometimes: solo valida si está presente
            'nombre_usuario' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('usuarios', 'nombre_usuario')->ignore($usuario->id), // Ignora el usuario actual al chequear unicidad
            ],
            'correo_electronico' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('usuarios', 'correo_electronico')->ignore($usuario->id), // Ignora el usuario actual
            ],
            'contrasena' => 'sometimes|nullable|string|min:6', // Permite nulo o vacío para no cambiarla
            'rol' => 'sometimes|required|string|in:ADMIN,USUARIO'
        ]);

        // --- ACTUALIZAR CONTRASEÑA (SOLO SI SE PROPORCIONA UNA NUEVA) ---
        if (!empty($validatedData['contrasena'])) {
            $validatedData['contrasena'] = Hash::make($validatedData['contrasena']);
        } else {
            // Si la contraseña viene vacía o nula, la eliminamos del array
            // para que no intente actualizarla en la BD con un valor inválido.
            unset($validatedData['contrasena']);
        }

        try {
            $usuario->update($validatedData);
            Log::info('Usuario actualizado por admin:', ['id' => $usuario->id, 'admin_id' => optional($request->user())->id]);
            return response()->json($usuario);
        } catch (\Exception $e) {
            Log::error('Error en UsuarioController@update:', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Error interno al actualizar usuario'], 500);
        }
    }

    public function destroy(Usuario $usuario)
    {
        $usuario->delete();
        return response()->noContent();
    }
}
