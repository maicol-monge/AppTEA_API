<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function index()
    {
        return response()->json(Usuario::all());
    }

    public function show($id)
    {
        $user = Usuario::findOrFail($id);
        return response()->json($user);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'correo' => 'required|email|unique:usuario,correo',
            'contrasena' => 'required|string',
        ]);

        $data['contrasena'] = bcrypt($data['contrasena']);
        $user = Usuario::create($data);
        return response()->json($user, 201);
    }

    public function update(Request $request, $id)
    {
        $user = Usuario::findOrFail($id);
        $data = $request->only(['nombres', 'apellidos', 'direccion', 'telefono', 'correo', 'imagen', 'estado']);
        $user->update($data);
        return response()->json($user);
    }

    public function destroy($id)
    {
        $user = Usuario::findOrFail($id);
        $user->delete();
        return response()->json(null, 204);
    }
}
