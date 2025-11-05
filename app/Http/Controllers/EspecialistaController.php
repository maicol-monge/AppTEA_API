<?php

namespace App\Http\Controllers;

use App\Models\Especialista;
use App\Models\Usuario;
use App\Models\Paciente;
use App\Models\TestAdir;
use App\Models\TestAdos;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EspecialistaController extends Controller
{
    public function index()
    {
        return response()->json(Especialista::with('usuario')->get());
    }

    public function show($id)
    {
        $esp = Especialista::with('usuario')->findOrFail($id);
        return response()->json($esp);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'especialidad' => 'required|string|max:100',
        ]);

        $esp = Especialista::create($data);
        return response()->json($esp, 201);
    }

    public function update(Request $request, $id)
    {
        $esp = Especialista::findOrFail($id);
        $data = $request->only(['especialidad', 'terminos_privacida']);
        $esp->update($data);
        return response()->json($esp);
    }

    public function destroy($id)
    {
        $esp = Especialista::findOrFail($id);
        $esp->delete();
        return response()->json(null, 204);
    }

    public function buscarEspecialistaPorUsuario($id_usuario)
    {
        if (!$id_usuario)
            return response()->json(['message' => 'El id_usuario es requerido'], 400);

        $especialista = Especialista::where('id_usuario', $id_usuario)->first();
        if (!$especialista)
            return response()->json(['message' => 'Especialista no encontrado'], 404);

        // generate simple API token
        $token = Str::random(60);
        $expires = Carbon::now()->addHours(2);
        ApiToken::create([
            'id_usuario' => $especialista->id_usuario,
            'token' => hash('sha256', $token),
            'expires_at' => $expires,
        ]);

        return response()->json([
            'message' => 'Especialista encontrado exitosamente',
            'token' => $token,
            'especialista' => [
                'id_especialista' => $especialista->id_especialista,
                'id_usuario' => $especialista->id_usuario,
                'especialidad' => $especialista->especialidad,
                'terminos_privacida' => $especialista->terminos_privacida,
            ],
        ]);
    }

    public function aceptarConsentimientoEspecialista(Request $request)
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'correo' => 'required|email',
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
        ]);

        Especialista::where('id_usuario', $data['id_usuario'])->update(['terminos_privacida' => 1]);
        try {
            Mail::raw("Consentimiento profesional aceptado", function ($message) use ($data) {
                $message->to($data['correo'])->subject('Consentimiento profesional aceptado - TEA');
            });
        } catch (\Throwable $e) {
        }

        return response()->json(['message' => 'Consentimiento registrado y correo enviado']);
    }

    public function pacientesConTests()
    {
        // Return list of patients with last ADIR/ADOS dates
        $pacientes = Paciente::join('usuario as u', 'paciente.id_usuario', '=', 'u.id_usuario')
            ->selectRaw("paciente.id_paciente, u.nombres, u.apellidos, u.imagen, paciente.fecha_nacimiento, paciente.sexo")
            ->get();

        // augment with dates
        $result = $pacientes->map(function ($p) {
            $fecha_ultimo_adir = TestAdir::where('id_paciente', $p->id_paciente)->where('estado', 1)->max('fecha');
            $fecha_ultimo_ados = TestAdos::where('id_paciente', $p->id_paciente)->where('estado', 0)->max('fecha');
            return array_merge((array) $p->toArray(), [
                'fecha_ultimo_adir' => $fecha_ultimo_adir,
                'fecha_ultimo_ados' => $fecha_ultimo_ados,
            ]);
        });

        return response()->json($result);
    }
}
