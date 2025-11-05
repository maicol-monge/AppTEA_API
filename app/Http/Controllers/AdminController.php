<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Usuario;
use App\Models\Paciente;
use App\Models\Especialista;
use App\Models\Area;
use App\Models\PreguntaAdi;
use App\Models\Actividad;
use App\Models\TestAdir;
use App\Models\TestAdos;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // Usuarios
    public function getUsuarios()
    {
        return response()->json(Usuario::all());
    }

    public function createUsuario(Request $request)
    {
        $data = $request->validate([
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string',
            'correo' => 'required|email|unique:usuario,correo',
            'contrasena' => 'required|string',
            'privilegio' => 'required|integer',
            'imagen' => 'nullable|string',
        ]);

        $data['contrasena'] = Hash::make($data['contrasena']);
        $data['requiere_cambio_contrasena'] = 1;
        $data['estado'] = 1;

        $user = Usuario::create($data);
        return response()->json(['id_usuario' => $user->id_usuario]);
    }

    public function updateUsuario(Request $request, $id_usuario)
    {
        $user = Usuario::findOrFail($id_usuario);
        $data = $request->only(['nombres', 'apellidos', 'direccion', 'telefono', 'correo', 'privilegio', 'imagen', 'estado']);
        $user->update($data);

        // If reactivated, send reactivation email
        if (isset($data['estado']) && $data['estado'] == 1) {
            try {
                Mail::raw("Tu cuenta ha sido reactivada.", function ($message) use ($user) {
                    $message->to($user->correo)->subject('Cuenta reactivada');
                });
            } catch (\Throwable $e) {
            }
        }

        return response()->json(['message' => 'Usuario actualizado']);
    }

    public function deleteUsuario($id_usuario)
    {
        Usuario::destroy($id_usuario);
        return response()->json(['message' => 'Usuario eliminado']);
    }

    // Pacientes
    public function getPacientes()
    {
        return response()->json(Paciente::all());
    }

    public function createPaciente(Request $request)
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'fecha_nacimiento' => 'nullable|date',
            'sexo' => 'nullable|string',
        ]);
        $p = Paciente::create($data);
        return response()->json(['id_paciente' => $p->id_paciente]);
    }

    public function updatePaciente(Request $request, $id_paciente)
    {
        $p = Paciente::findOrFail($id_paciente);
        $p->update($request->only(['fecha_nacimiento', 'sexo', 'filtro_dsm_5', 'terminos_privacida']));
        return response()->json(['message' => 'Paciente actualizado']);
    }

    public function deletePaciente($id_paciente)
    {
        Paciente::destroy($id_paciente);
        return response()->json(['message' => 'Paciente eliminado']);
    }

    // Especialistas
    public function getEspecialistas()
    {
        return response()->json(Especialista::all());
    }

    public function createEspecialista(Request $request)
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'especialidad' => 'required|string',
        ]);
        $e = Especialista::create($data);
        return response()->json(['id_especialista' => $e->id_especialista]);
    }

    public function updateEspecialista(Request $request, $id_especialista)
    {
        $e = Especialista::findOrFail($id_especialista);
        $e->update($request->only(['especialidad', 'terminos_privacida']));
        return response()->json(['message' => 'Especialista actualizado']);
    }

    public function deleteEspecialista($id_especialista)
    {
        Especialista::destroy($id_especialista);
        return response()->json(['message' => 'Especialista eliminado']);
    }

    // Areas
    public function getAreas()
    {
        return response()->json(Area::all());
    }

    public function createArea(Request $request)
    {
        $data = $request->validate(['area' => 'required|string']);
        $a = Area::create($data);
        return response()->json(['id_area' => $a->id_area]);
    }

    public function updateArea(Request $request, $id_area)
    {
        $a = Area::findOrFail($id_area);
        $a->update($request->only(['area']));
        return response()->json(['message' => 'Área actualizada']);
    }

    public function deleteArea($id_area)
    {
        Area::destroy($id_area);
        return response()->json(['message' => 'Área eliminada']);
    }

    // Preguntas ADI
    public function getPreguntas()
    {
        return response()->json(PreguntaAdi::all());
    }

    public function createPregunta(Request $request)
    {
        $data = $request->validate(['pregunta' => 'required|string', 'id_area' => 'required|integer|exists:area,id_area']);
        $p = PreguntaAdi::create($data);
        return response()->json(['id_pregunta' => $p->id_pregunta]);
    }

    public function updatePregunta(Request $request, $id_pregunta)
    {
        $p = PreguntaAdi::findOrFail($id_pregunta);
        $p->update($request->only(['pregunta', 'id_area']));
        return response()->json(['message' => 'Pregunta actualizada']);
    }

    public function deletePregunta($id_pregunta)
    {
        PreguntaAdi::destroy($id_pregunta);
        return response()->json(['message' => 'Pregunta eliminada']);
    }

    // Actividades
    public function getActividades()
    {
        return response()->json(Actividad::all());
    }

    public function createActividad(Request $request)
    {
        $data = $request->validate([
            'id_ados' => 'required|integer',
            'nombre_actividad' => 'required|string',
            'observacion' => 'nullable|string',
            'puntuacion' => 'nullable|numeric',
        ]);
        $a = Actividad::create($data);
        return response()->json(['id_actividad' => $a->id_actividad]);
    }

    public function updateActividad(Request $request, $id_actividad)
    {
        $a = Actividad::findOrFail($id_actividad);
        $a->update($request->only(['id_ados', 'nombre_actividad', 'observacion', 'puntuacion']));
        return response()->json(['message' => 'Actividad actualizada']);
    }

    public function deleteActividad($id_actividad)
    {
        Actividad::destroy($id_actividad);
        return response()->json(['message' => 'Actividad eliminada']);
    }

    // Tests ADI-R
    public function getTestsAdiR()
    {
        return response()->json(TestAdir::all());
    }

    public function updateTestAdiR(Request $request, $id_adir)
    {
        $t = TestAdir::findOrFail($id_adir);
        $t->update($request->only(['id_paciente', 'id_especialista', 'fecha', 'diagnostico']));
        return response()->json(['message' => 'Test ADI-R actualizado']);
    }

    public function deleteTestAdiR($id_adir)
    {
        TestAdir::destroy($id_adir);
        return response()->json(['message' => 'Test ADI-R eliminado']);
    }

    // Tests ADOS-2
    public function getTestsAdos2()
    {
        return response()->json(TestAdos::all());
    }

    public function updateTestAdos2(Request $request, $id_ados)
    {
        $t = TestAdos::findOrFail($id_ados);
        $t->update($request->only(['id_paciente', 'fecha', 'modulo', 'id_especialista', 'diagnostico', 'total_punto']));
        return response()->json(['message' => 'Test ADOS-2 actualizado']);
    }

    public function deleteTestAdos2($id_ados)
    {
        TestAdos::destroy($id_ados);
        return response()->json(['message' => 'Test ADOS-2 eliminado']);
    }
}
