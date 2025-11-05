<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\Usuario;
use App\Models\Paciente;
use App\Models\Especialista;
use App\Models\ApiToken;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
            'contrasena' => 'required|string',
        ]);

        $user = Usuario::where('correo', $request->input('correo'))->first();
        if (!$user) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if (!Hash::check($request->input('contrasena'), $user->contrasena)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // create token
        $token = Str::random(60);
        $expires = Carbon::now()->addHours(8);
        ApiToken::create([
            'id_usuario' => $user->id_usuario,
            'token' => hash('sha256', $token),
            'expires_at' => $expires,
        ]);

        // return raw token to client (store hashed in DB)
        return response()->json([
            'token' => $token,
            'expires_at' => $expires->toDateTimeString(),
            'usuario' => [
                'id_usuario' => $user->id_usuario,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'correo' => $user->correo,
            ],
        ]);
    }

    public function registrar(Request $request)
    {
        // This route in original Node required authentication (admin). Here we allow creation but expect caller to be authorized via middleware.
        $data = $request->validate([
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'direccion' => 'required|string|max:100',
            'telefono' => 'required|string|max:20',
            'correo' => 'required|email|unique:usuario,correo',
            'privilegio' => 'required|integer',
            'imagen' => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date',
            'sexo' => 'nullable|string|in:M,F',
            'especialidad' => 'nullable|string|max:100',
        ]);

        // generate password and hash
        $plain = Str::random(12);
        $hashed = Hash::make($plain);

        $user = Usuario::create([
            'nombres' => $data['nombres'],
            'apellidos' => $data['apellidos'],
            'direccion' => $data['direccion'],
            'telefono' => $data['telefono'],
            'correo' => $data['correo'],
            'contrasena' => $hashed,
            'requiere_cambio_contrasena' => 1,
            'privilegio' => $data['privilegio'],
            'imagen' => $data['imagen'] ?? null,
            'estado' => 1,
        ]);

        // If paciente
        if (isset($data['fecha_nacimiento']) && isset($data['sexo']) && $data['privilegio'] == 0) {
            Paciente::create([
                'id_usuario' => $user->id_usuario,
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'sexo' => $data['sexo'],
            ]);
        }

        // If especialista
        if ($data['privilegio'] == 1 && isset($data['especialidad'])) {
            Especialista::create([
                'id_usuario' => $user->id_usuario,
                'especialidad' => $data['especialidad'],
            ]);
        }

        // send welcome email (plain)
        try {
            Mail::raw("Bienvenido {$user->nombres} {$user->apellidos}\nUsuario: {$user->correo}\nContraseña: {$plain}", function ($message) use ($user) {
                $message->to($user->correo)->subject('Bienvenido - Aplicación TEA');
            });
        } catch (\Throwable $e) {
            // ignore mail failures for now
        }

        return response()->json(['usuario' => $user], 201);
    }

    public function cambiarContrasena(Request $request)
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'nuevaContra' => ['required', 'string', 'min:8'],
        ]);

        $user = Usuario::findOrFail($data['id_usuario']);
        $user->contrasena = Hash::make($data['nuevaContra']);
        $user->requiere_cambio_contrasena = 0;
        $user->save();

        // send confirmation email
        try {
            Mail::raw("Tu contraseña ha sido actualizada.", function ($message) use ($user) {
                $message->to($user->correo)->subject('Cambio de contraseña');
            });
        } catch (\Throwable $e) {
        }

        return response()->json(['message' => 'Contraseña actualizada']);
    }

    public function cambiarPasswordConActual(Request $request)
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'currentPassword' => 'required|string',
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        $user = Usuario::findOrFail($data['id_usuario']);
        if (!Hash::check($data['currentPassword'], $user->contrasena)) {
            return response()->json(['message' => 'Contraseña actual incorrecta'], 400);
        }

        $user->contrasena = Hash::make($data['newPassword']);
        $user->requiere_cambio_contrasena = 0;
        $user->save();

        return response()->json(['message' => 'Contraseña cambiada']);
    }

    public function recuperarContrasena(Request $request)
    {
        $data = $request->validate(['correo' => 'required|email']);
        $user = Usuario::where('correo', $data['correo'])->first();
        if (!$user)
            return response()->json(['message' => 'Correo no encontrado'], 404);

        // generate temporary password
        $plain = Str::random(12);
        $user->contrasena = Hash::make($plain);
        $user->requiere_cambio_contrasena = 1;
        $user->save();

        try {
            Mail::raw("Se ha generado una nueva contraseña: {$plain}", function ($message) use ($user) {
                $message->to($user->correo)->subject('Recuperación de contraseña');
            });
        } catch (\Throwable $e) {
        }

        return response()->json(['message' => 'Contraseña enviada al correo si existe.']);
    }

    public function listarPacientes(Request $request)
    {
        // list patients join usuario
        $pacientes = Paciente::with('usuario')->get()->map(function ($p) {
            return [
                'id_paciente' => $p->id_paciente,
                'nombres' => $p->usuario->nombres ?? null,
                'apellidos' => $p->usuario->apellidos ?? null,
                'sexo' => $p->sexo,
                'fecha_nacimiento' => $p->fecha_nacimiento,
            ];
        });

        return response()->json($pacientes);
    }
}
