<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Usuario;
use App\Models\TestAdir;
use App\Models\TestAdos;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class PacienteController extends Controller
{
    public function index()
    {
        return response()->json(Paciente::with('usuario')->get());
    }

    public function show($id)
    {
        $paciente = Paciente::with('usuario')->findOrFail($id);
        return response()->json($paciente);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'fecha_nacimiento' => 'required|date',
            'sexo' => 'required|string|in:M,F',
        ]);

        $paciente = Paciente::create($data);
        return response()->json($paciente, 201);
    }

    public function update(Request $request, $id)
    {
        $paciente = Paciente::findOrFail($id);
        $data = $request->only(['fecha_nacimiento', 'sexo', 'filtro_dsm_5', 'terminos_privacida']);
        $paciente->update($data);
        return response()->json($paciente);
    }

    public function destroy($id)
    {
        $paciente = Paciente::findOrFail($id);
        $paciente->delete();
        return response()->json(null, 204);
    }

    // Buscar paciente por id_usuario (retorna paciente si existe)
    public function buscarPacientePorUsuario($id_usuario)
    {
        $paciente = Paciente::where('id_usuario', $id_usuario)->with('usuario')->first();
        if (!$paciente)
            return response()->json(['message' => 'Paciente no encontrado'], 404);
        return response()->json($paciente);
    }

    public function aceptarConsentimiento(Request $request)
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'correo' => 'required|email',
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
        ]);

        $updated = Paciente::where('id_usuario', $data['id_usuario'])->update(['terminos_privacida' => 1]);
        try {
            Mail::raw("Consentimiento aceptado", function ($message) use ($data) {
                $message->to($data['correo'])->subject('Consentimiento aceptado - TEA');
            });
        } catch (\Throwable $e) {
        }

        return response()->json(['message' => 'Consentimiento registrado']);
    }

    public function guardarDsm5(Request $request)
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'resultado' => 'required|string',
        ]);

        $valor = $data['resultado'] === 'Se recomienda aplicar las pruebas ADOS-2 y ADI-R.' ? 1 : 0;
        $updated = Paciente::where('id_usuario', $data['id_usuario'])->update(['filtro_dsm_5' => $valor]);
        return response()->json(['message' => 'Resultado guardado']);
    }

    public function validarTerminos($id_usuario)
    {
        $paciente = Paciente::where('id_usuario', $id_usuario)->first();
        if (!$paciente)
            return response()->json(['terminos_privacida' => 0]);
        return response()->json(['terminos_privacida' => (int) $paciente->terminos_privacida]);
    }

    public function desactivarCuenta($id_usuario)
    {
        $user = Usuario::where('id_usuario', $id_usuario)->first();
        if (!$user)
            return response()->json(['message' => 'Usuario no encontrado'], 404);

        Usuario::where('id_usuario', $id_usuario)->update(['estado' => 0]);

        try {
            Mail::raw("Tu cuenta ha sido desactivada.", function ($message) use ($user) {
                $message->to($user->correo)->subject('Cuenta desactivada');
            });
        } catch (\Throwable $e) {
        }

        return response()->json(['message' => 'Cuenta desactivada']);
    }

    // Listar resultados ADI-R y ADOS-2 para un paciente con filtros opcionales
    public function listarResultadosPaciente($id_paciente, Request $request)
    {
        $tipo = $request->query('tipo');
        $fecha_inicio = $request->query('fecha_inicio');
        $fecha_fin = $request->query('fecha_fin');

        $results = [];

        if (!$tipo || $tipo === 'adir') {
            $query = TestAdir::where('id_paciente', $id_paciente)->orderBy('fecha', 'desc');
            if ($fecha_inicio)
                $query->where('fecha', '>=', $fecha_inicio);
            if ($fecha_fin)
                $query->where('fecha', '<=', $fecha_fin);
            $results['adir'] = $query->get();
        }

        if (!$tipo || $tipo === 'ados') {
            $query2 = TestAdos::where('id_paciente', $id_paciente)->orderBy('fecha', 'desc');
            if ($fecha_inicio)
                $query2->where('fecha', '>=', $fecha_inicio);
            if ($fecha_fin)
                $query2->where('fecha', '<=', $fecha_fin);
            $results['ados'] = $query2->get();
        }

        return response()->json($results);
    }
}
