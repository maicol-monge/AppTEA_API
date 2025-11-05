<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Usuario;
use App\Models\Paciente;
use App\Models\Especialista;
use App\Models\ApiToken;
use Carbon\Carbon;

class AuthController extends Controller
{
    private function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function createJwt(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            $this->base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $this->base64url_encode($signature);
        return implode('.', $segments);
    }
    /**
     * Dev-only helper to create or update an admin user (privilegio=3).
     * Only enabled in APP_ENV=local to bootstrap the system.
     */
    public function bootstrapAdmin(Request $request)
    {
        if (!app()->environment('local')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'correo' => 'required|email',
            'contrasena' => 'required|string|min:8',
            'nombres' => 'nullable|string|max:100',
            'apellidos' => 'nullable|string|max:100',
        ]);

        $user = Usuario::where('correo', $data['correo'])->first();
        if ($user) {
            $user->contrasena = Hash::make($data['contrasena']);
            $user->privilegio = 3; // admin
            $user->estado = 1;
            $user->requiere_cambio_contrasena = 0;
            if (!empty($data['nombres']))
                $user->nombres = $data['nombres'];
            if (!empty($data['apellidos']))
                $user->apellidos = $data['apellidos'];
            $user->save();
        } else {
            $user = Usuario::create([
                'nombres' => $data['nombres'] ?? 'Admin',
                'apellidos' => $data['apellidos'] ?? 'Local',
                'direccion' => '',
                'telefono' => '',
                'correo' => $data['correo'],
                'contrasena' => Hash::make($data['contrasena']),
                'requiere_cambio_contrasena' => 0,
                'privilegio' => 3,
                'imagen' => null,
                'estado' => 1,
            ]);
        }

        return response()->json([
            'message' => 'Admin listo',
            'usuario' => [
                'id_usuario' => $user->id_usuario,
                'correo' => $user->correo,
                'privilegio' => $user->privilegio,
            ],
        ], 201);
    }
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

        // Estado de cuenta desactivada
        if (isset($user->estado) && (int) $user->estado === 0) {
            return response()->json([
                'message' => 'Tu cuenta está desactivada. Por favor, contacta al administrador al correo aplicaciondediagnosticodetea@gmail.com'
            ], 403);
        }

        $plainPassword = $request->input('contrasena');
        $storedPassword = is_string($user->contrasena) ? trim($user->contrasena) : $user->contrasena;
        // Debug de formato (sin exponer la contraseña)
        try {
            $len = is_string($storedPassword) ? strlen($storedPassword) : 0;
            $prefix = is_string($storedPassword) ? substr($storedPassword, 0, 4) : 'null';
            Log::debug("Login: formato de hash para usuario {$user->id_usuario}: len={$len}, prefix={$prefix}");
        } catch (\Throwable $e) {
        }

        $isValid = false;

        try {
            // Basic checks
            if (!is_string($storedPassword) || $storedPassword === '') {
                $isValid = false;
            } elseif (preg_match('/^\$2[aby]\$\d{2}\$.{53}$/', $storedPassword)) {
                // bcrypt format
                $isValid = Hash::check($plainPassword, $storedPassword);
            } else {
                // Legacy/plaintext password: compare in timing-safe way and upgrade to bcrypt
                if (is_string($storedPassword) && hash_equals($storedPassword, $plainPassword)) {
                    $isValid = true;
                    // Re-hash the plaintext password to bcrypt and save it
                    $user->contrasena = Hash::make($plainPassword);
                    $user->save();
                } elseif (
                    is_string($storedPassword)
                    && preg_match('/^[a-f0-9]{32}$/i', $storedPassword)
                    && hash_equals($storedPassword, md5($plainPassword))
                ) {
                    // Compatibilidad con posibles hashes md5 heredados
                    $isValid = true;
                    $user->contrasena = Hash::make($plainPassword);
                    $user->save();
                } elseif (
                    is_string($storedPassword)
                    && preg_match('/^[a-f0-9]{40}$/i', $storedPassword)
                    && hash_equals($storedPassword, sha1($plainPassword))
                ) {
                    // Compatibilidad con posibles hashes sha1 heredados
                    $isValid = true;
                    $user->contrasena = Hash::make($plainPassword);
                    $user->save();
                }
            }
        } catch (\Throwable $e) {
            // Log full exception for diagnosis (no sensitive plaintext)
            Log::error('Error verifying password for user id ' . ($user->id_usuario ?? 'unknown') . ': ' . $e->getMessage());
            // Return unauthorized instead of 500 to avoid revealing internal state
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if (!$isValid) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // Crear JWT compatible con Node (expira en 2h)
        $exp = Carbon::now()->addHours(2)->timestamp;
        $jwtPayload = [
            'id_usuario' => $user->id_usuario,
            'correo' => $user->correo,
            'privilegio' => $user->privilegio,
            'exp' => $exp,
        ];
        $secret = (string) env('JWT_SECRET', 'local-dev-secret');
        $token = $this->createJwt($jwtPayload, $secret);
        // Respuesta compatible con Node
        if (isset($user->requiere_cambio_contrasena) && (int) $user->requiere_cambio_contrasena === 1) {
            return response()->json([
                'message' => 'Contraseña genérica detectada, debe cambiarla',
                'requirePasswordChange' => true,
                'token' => $token,
                'user' => [
                    'id_usuario' => $user->id_usuario,
                    'correo' => $user->correo,
                ]
            ]);
        }

        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'requirePasswordChange' => false,
            'token' => $token,
            'user' => [
                'id_usuario' => $user->id_usuario,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'direccion' => $user->direccion ?? null,
                'telefono' => $user->telefono ?? null,
                'correo' => $user->correo,
                'privilegio' => $user->privilegio ?? null,
                'imagen' => $user->imagen ?? null,
                'estado' => $user->estado ?? null,
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
            Log::info('Enviando correo de bienvenida a ' . $user->correo);
            Mail::raw("Bienvenido {$user->nombres} {$user->apellidos}\nUsuario: {$user->correo}\nContraseña: {$plain}", function ($message) use ($user) {
                $message->to($user->correo)->subject('Bienvenido - Aplicación TEA');
            });
        } catch (\Throwable $e) {
            Log::error('Fallo enviando correo de bienvenida: ' . $e->getMessage());
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
            Log::info('Enviando correo de confirmación de cambio de contraseña a ' . $user->correo);
            Mail::raw("Tu contraseña ha sido actualizada.", function ($message) use ($user) {
                $message->to($user->correo)->subject('Cambio de contraseña');
            });
        } catch (\Throwable $e) {
            Log::error('Fallo enviando correo de cambio de contraseña: ' . $e->getMessage());
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
            Log::info('Enviando correo de recuperación de contraseña a ' . $user->correo);
            Mail::raw("Se ha generado una nueva contraseña: {$plain}", function ($message) use ($user) {
                $message->to($user->correo)->subject('Recuperación de contraseña');
            });
        } catch (\Throwable $e) {
            Log::error('Fallo enviando correo de recuperación: ' . $e->getMessage());
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
