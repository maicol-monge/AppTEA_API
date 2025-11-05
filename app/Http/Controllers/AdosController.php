<?php

namespace App\Http\Controllers;

use App\Models\TestAdos;
use App\Models\Paciente;
use App\Models\Actividad;
use App\Models\ActividadRealizada;
use App\Models\PuntuacionAplicada;
use App\Models\PuntuacionCodificacion;
use App\Models\Codificacion;
use App\Models\Item;
use App\Models\ItemAlgoritmo;
use App\Models\Algoritmo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AdosController extends Controller
{
    public function index()
    {
        return response()->json(TestAdos::with(['paciente', 'especialista'])->get());
    }

    public function show($id)
    {
        $item = TestAdos::with(['paciente', 'especialista'])->findOrFail($id);
        return response()->json($item);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_paciente' => 'required|integer|exists:paciente,id_paciente',
            'fecha' => 'required|date',
            'modulo' => 'required|string',
            'id_especialista' => 'nullable|integer|exists:especialista,id_especialista',
            'estado' => 'nullable|integer',
        ]);

        $data['estado'] = $data['estado'] ?? 1;
        $item = TestAdos::create($data);
        return response()->json(['id_ados' => $item->id_ados]);
    }

    public function update(Request $request, $id)
    {
        $item = TestAdos::findOrFail($id);
        $data = $request->only(['diagnostico', 'total_punto', 'clasificacion', 'puntuacion_comparativa', 'estado', 'modulo', 'id_paciente', 'id_especialista']);
        $item->update($data);
        return response()->json(['message' => 'Test ADOS-2 actualizado']);
    }

    public function destroy($id)
    {
        TestAdos::destroy($id);
        return response()->json(['message' => 'Test ADOS-2 eliminado']);
    }

    // Listar todos los pacientes (con opción de ver sus tests ADOS-2)
    public function listarPacientesConAdos()
    {
        $pacientes = DB::select("SELECT p.id_paciente, u.nombres, u.apellidos, p.sexo, p.fecha_nacimiento FROM paciente p JOIN usuario u ON p.id_usuario = u.id_usuario ORDER BY u.apellidos, u.nombres");
        return response()->json($pacientes);
    }

    // Listar tests ADOS-2 por paciente
    public function listarTestsAdosPorPaciente($id_paciente)
    {
        $results = DB::select("SELECT t.id_ados, t.fecha, t.modulo, t.diagnostico, t.total_punto, t.clasificacion, t.puntuacion_comparativa, t.estado, t.id_paciente FROM test_ados_2 t WHERE t.id_paciente = ? ORDER BY t.fecha DESC", [$id_paciente]);
        return response()->json($results);
    }

    // Listar actividades por módulo
    public function listarActividadesPorModulo($modulo)
    {
        $results = DB::select("SELECT id_actividad, nombre_actividad, objetivo, CAST(materiales AS CHAR) AS materiales, CAST(intrucciones AS CHAR) AS intrucciones, CAST(aspectos_observar AS CHAR) AS aspectos_observar, CAST(info_complementaria AS CHAR) AS info_complementaria FROM actividad WHERE modulo = ? ORDER BY id_actividad", [$modulo]);
        return response()->json($results);
    }

    // Crear un nuevo test ADOS-2 (ya implementado en store, mantengo alias)
    public function crearTestAdos(Request $request)
    {
        return $this->store($request);
    }

    // Validar filtros de paciente
    public function validarFiltrosPaciente($id_paciente)
    {
        $row = DB::select('SELECT terminos_privacida, filtro_dsm_5 FROM paciente WHERE id_paciente = ?', [$id_paciente]);
        if (empty($row))
            return response()->json(['permitido' => false, 'message' => 'Paciente no encontrado.']);
        $r = (array) $row[0];
        if ($r['terminos_privacida'] != 1 || $r['filtro_dsm_5'] != 1)
            return response()->json(['permitido' => false, 'message' => 'El paciente no ha aceptado los términos de privacidad o no cumple el filtro DSM-5.']);
        return response()->json(['permitido' => true]);
    }

    // Guardar actividad realizada (insert or update)
    public function guardarActividadRealizada(Request $request)
    {
        $data = $request->validate([
            'id_ados' => 'required|integer',
            'id_actividad' => 'required|integer',
            'observacion' => 'nullable|string',
        ]);

        DB::table('actividad_realizada')->updateOrInsert(
            ['id_ados' => $data['id_ados'], 'id_actividad' => $data['id_actividad']],
            ['observacion' => $data['observacion'] ?? '']
        );

        return response()->json(['message' => 'Observación guardada.']);
    }

    // Obtener observaciones previas
    public function obtenerActividadesRealizadas($id_ados)
    {
        $results = DB::select('SELECT id_actividad, observacion FROM actividad_realizada WHERE id_ados = ?', [$id_ados]);
        return response()->json($results);
    }

    // Pausar o finalizar test
    public function pausarTestAdos($id_ados, Request $request)
    {
        $estado = $request->input('estado');
        DB::update('UPDATE test_ados_2 SET estado = ? WHERE id_ados = ?', [$estado, $id_ados]);
        return response()->json(['message' => 'Test actualizado']);
    }

    // Buscar test pausado
    public function buscarTestPausado(Request $request)
    {
        $id_paciente = $request->query('id_paciente');
        $modulo = $request->query('modulo');
        $id_especialista = $request->query('id_especialista');

        $res = DB::select('SELECT id_ados FROM test_ados_2 WHERE id_paciente = ? AND modulo = ? AND id_especialista = ? AND estado = 1 ORDER BY fecha DESC LIMIT 1', [$id_paciente, $modulo, $id_especialista]);
        if (!empty($res))
            return response()->json(['id_ados' => $res[0]->id_ados]);
        return response()->json(new \stdClass());
    }

    // Responder item (insert/update puntaje)
    public function responderItem(Request $request)
    {
        $data = $request->validate([
            'id_ados' => 'required|integer',
            'id_item' => 'required|integer',
            'puntaje' => 'required|numeric',
        ]);

        DB::table('puntuacion_aplicada')->updateOrInsert(
            ['id_item' => $data['id_item'], 'id_ados' => $data['id_ados']],
            ['puntaje' => $data['puntaje']]
        );

        return response()->json(['message' => 'Respuesta guardada.']);
    }

    // Responder codificacion (similar a Node logic)
    public function responderCodificacion(Request $request)
    {
        $data = $request->validate([
            'id_ados' => 'required|integer',
            'id_puntuacion_codificacion' => 'required|integer',
        ]);

        $row = DB::select('SELECT id_codificacion FROM puntuacion_codificacion WHERE id_puntuacion_codificacion = ?', [$data['id_puntuacion_codificacion']]);
        if (empty($row))
            return response()->json(['message' => 'Puntuación inválida'], 400);
        $id_codificacion = $row[0]->id_codificacion;

        $existing = DB::select("SELECT pa.id_puntuacion_aplicada FROM puntuacion_aplicada pa JOIN puntuacion_codificacion pc ON pa.id_puntuacion_codificacion = pc.id_puntuacion_codificacion WHERE pa.id_ados = ? AND pc.id_codificacion = ?", [$data['id_ados'], $id_codificacion]);

        if (!empty($existing)) {
            DB::update('UPDATE puntuacion_aplicada SET id_puntuacion_codificacion = ? WHERE id_puntuacion_aplicada = ?', [$data['id_puntuacion_codificacion'], $existing[0]->id_puntuacion_aplicada]);
            return response()->json(['message' => 'Respuesta actualizada']);
        }

        DB::table('puntuacion_aplicada')->insert(['id_puntuacion_codificacion' => $data['id_puntuacion_codificacion'], 'id_ados' => $data['id_ados']]);
        return response()->json(['message' => 'Respuesta guardada']);
    }

    public function puntuacionesPorCodificacion($id_codificacion)
    {
        $results = PuntuacionCodificacion::where('id_codificacion', $id_codificacion)->get();
        return response()->json($results);
    }

    public function obtenerPacientePorId($id_paciente)
    {
        $p = Paciente::find($id_paciente);
        if (!$p)
            return response()->json(['message' => 'Paciente no encontrado.'], 404);
        return response()->json($p);
    }

    public function codificacionPorId($id_codificacion)
    {
        $c = Codificacion::find($id_codificacion);
        if (!$c)
            return response()->json(['message' => 'Codificación no encontrada.'], 404);
        return response()->json($c);
    }

    public function codificacionesPorAlgoritmo($id_algoritmo)
    {
        $results = DB::select("SELECT c.* FROM item_algoritmo ia JOIN item i ON ia.id_item = i.id_item JOIN codificacion c ON i.id_codificacion = c.id_codificacion WHERE ia.id_algoritmo = ?", [$id_algoritmo]);
        return response()->json($results);
    }

    public function obtenerAlgoritmoPorId($id_algoritmo)
    {
        $alg = Algoritmo::find($id_algoritmo);
        if (!$alg)
            return response()->json(['message' => 'Algoritmo no encontrado.'], 404);
        return response()->json($alg);
    }

    public function respuestasAlgoritmo($id_ados)
    {
        $results = DB::select('SELECT pc.id_codificacion, pa.id_puntuacion_codificacion FROM puntuacion_aplicada pa JOIN puntuacion_codificacion pc ON pa.id_puntuacion_codificacion = pc.id_puntuacion_codificacion WHERE pa.id_ados = ?', [$id_ados]);
        return response()->json($results);
    }

    public function obtenerTestPorId($id_ados)
    {
        $t = TestAdos::find($id_ados);
        if (!$t)
            return response()->json(['message' => 'Test no encontrado'], 404);
        return response()->json($t);
    }

    public function obtenerAlgoritmoPorTest($id_ados)
    {
        $row = DB::select('SELECT t.modulo, p.fecha_nacimiento, t.fecha FROM test_ados_2 t JOIN paciente p ON t.id_paciente = p.id_paciente WHERE t.id_ados = ?', [$id_ados]);
        if (empty($row))
            return response()->json(['message' => 'Test no encontrado'], 404);
        $r = (array) $row[0];
        $modulo = $r['modulo'];
        $fecha_nacimiento = $r['fecha_nacimiento'];
        $fecha = $r['fecha'];

        if ($modulo === '1') {
            $res2 = DB::select('SELECT pc.puntaje FROM puntuacion_aplicada pa JOIN puntuacion_codificacion pc ON pa.id_puntuacion_codificacion = pc.id_puntuacion_codificacion WHERE pa.id_ados = ? AND pc.id_codificacion = 1 ORDER BY pa.id_puntuacion_aplicada DESC LIMIT 1', [$id_ados]);
            if (empty($res2))
                return response()->json(['message' => 'No se encontró respuesta para selección de algoritmo'], 404);
            $puntaje = $res2[0]->puntaje;
            $id_algoritmo = ($puntaje === 3 || $puntaje === 4) ? 1 : 2;
            return response()->json(['id_algoritmo' => $id_algoritmo]);
        } elseif ($modulo === '2') {
            $nacimiento = new Carbon($fecha_nacimiento);
            $testDate = new Carbon($fecha);
            $edad = $testDate->diffInYears($nacimiento);
            $id_algoritmo = $edad < 5 ? 3 : 4;
            return response()->json(['id_algoritmo' => $id_algoritmo]);
        } elseif ($modulo === '3') {
            return response()->json(['id_algoritmo' => 5]);
        } elseif ($modulo === '4') {
            return response()->json(['id_algoritmo' => 6]);
        } elseif ($modulo === 'T') {
            $nacimiento = new Carbon($fecha_nacimiento);
            $testDate = new Carbon($fecha);
            $meses = $testDate->diffInMonths($nacimiento);
            if ($meses >= 12 && $meses <= 20)
                $id_algoritmo = 7;
            else if ($meses >= 21 && $meses <= 30)
                $id_algoritmo = 8;
            else
                $id_algoritmo = null;
            return response()->json(['id_algoritmo' => $id_algoritmo]);
        }

        return response()->json(['message' => 'No se puede deducir el algoritmo para este módulo'], 400);
    }

    public function puntuacionesAplicadasPorTest($id_ados)
    {
        $results = DB::select('SELECT pa.id_puntuacion_aplicada, pc.puntaje, pc.id_codificacion FROM puntuacion_aplicada pa JOIN puntuacion_codificacion pc ON pa.id_puntuacion_codificacion = pc.id_puntuacion_codificacion WHERE pa.id_ados = ?', [$id_ados]);
        return response()->json($results);
    }

    public function actualizarClasificacion(Request $request, $id_ados)
    {
        $data = $request->validate(['clasificacion' => 'nullable|string', 'total_punto' => 'nullable|numeric']);
        DB::update('UPDATE test_ados_2 SET clasificacion = ?, total_punto = ? WHERE id_ados = ?', [$data['clasificacion'] ?? null, $data['total_punto'] ?? null, $id_ados]);
        return response()->json(['message' => 'Clasificación actualizada']);
    }

    public function actualizarPuntuacionComparativa(Request $request, $id_ados)
    {
        $data = $request->validate(['puntuacion_comparativa' => 'nullable|numeric']);
        DB::update('UPDATE test_ados_2 SET puntuacion_comparativa = ? WHERE id_ados = ?', [$data['puntuacion_comparativa'] ?? null, $id_ados]);
        return response()->json(['message' => 'Puntuación comparativa actualizada']);
    }

    public function actualizarDiagnostico(Request $request, $id_ados)
    {
        $data = $request->validate(['diagnostico' => 'nullable|string']);
        DB::update('UPDATE test_ados_2 SET diagnostico = ? WHERE id_ados = ?', [$data['diagnostico'] ?? null, $id_ados]);

        $row = DB::select('SELECT u.correo, u.nombres, u.apellidos FROM test_ados_2 t JOIN paciente p ON t.id_paciente = p.id_paciente JOIN usuario u ON p.id_usuario = u.id_usuario WHERE t.id_ados = ?', [$id_ados]);
        if (!empty($row)) {
            $r = (array) $row[0];
            try {
                Mail::raw("Hola {$r['nombres']} {$r['apellidos']},\n\nTe informamos que el diagnóstico de tu test ADOS-2 ha sido actualizado.", function ($message) use ($r) {
                    $message->to($r['correo'])->subject('Diagnóstico actualizado - Test ADOS-2');
                });
            } catch (\Throwable $e) {
            }
        }

        return response()->json(['message' => 'Diagnóstico actualizado y paciente notificado']);
    }

    public function obtenerActividadesPorTest($id_ados)
    {
        $results = DB::select("SELECT ar.id_actividad_realizada, ar.id_actividad, a.nombre_actividad, ar.observacion FROM actividad_realizada ar JOIN actividad a ON ar.id_actividad = a.id_actividad WHERE ar.id_ados = ? ORDER BY ar.id_actividad_realizada", [$id_ados]);
        return response()->json($results);
    }

    public function obtenerGrupoPorCodificacion($id_codificacion)
    {
        $res = DB::select('SELECT i.grupo FROM item i JOIN codificacion c ON c.id_codificacion = i.id_codificacion WHERE c.id_codificacion = ? LIMIT 1', [$id_codificacion]);
        if (empty($res))
            return response()->json(['message' => 'No se encontró grupo para ese id_codificacion'], 404);
        return response()->json(['grupo' => $res[0]->grupo]);
    }

    // Report helpers por módulo (T,1,2,3,4)
    public function obtenerDatosReporteModuloT($id_ados)
    {
        return $this->obtenerDatosReporteGenerico($id_ados, 'T');
    }

    public function obtenerDatosReporteModulo1($id_ados)
    {
        return $this->obtenerDatosReporteGenerico($id_ados, '1');
    }

    public function obtenerDatosReporteModulo3($id_ados)
    {
        return $this->obtenerDatosReporteGenerico($id_ados, '3');
    }

    public function obtenerDatosReporteModulo2($id_ados)
    {
        return $this->obtenerDatosReporteGenerico($id_ados, '2');
    }

    public function obtenerDatosReporteModulo4($id_ados)
    {
        return $this->obtenerDatosReporteGenerico($id_ados, '4');
    }

    private function obtenerDatosReporteGenerico($id_ados, $moduloEsperado)
    {
        // 1. Datos del test, paciente y especialista
        $sql = "SELECT t.id_ados, t.fecha, t.modulo, t.diagnostico, t.clasificacion, t.total_punto, t.puntuacion_comparativa, u.nombres AS nombres, u.apellidos AS apellidos, u.telefono, e.id_especialista, ue.nombres AS especialista_nombres, ue.apellidos AS especialista_apellidos, p.fecha_nacimiento FROM test_ados_2 t JOIN paciente p ON t.id_paciente = p.id_paciente JOIN usuario u ON p.id_usuario = u.id_usuario LEFT JOIN especialista e ON t.id_especialista = e.id_especialista LEFT JOIN usuario ue ON e.id_usuario = ue.id_usuario WHERE t.id_ados = ?";
        $rows = DB::select($sql, [$id_ados]);
        if (empty($rows))
            return response()->json(['message' => 'No se pudo obtener datos del test'], 500);
        $datos = (array) $rows[0];

        // 2. Puntuaciones aplicadas
        $puntuaciones = DB::select('SELECT pc.puntaje, pc.id_codificacion, c.codigo FROM puntuacion_aplicada pa JOIN puntuacion_codificacion pc ON pa.id_puntuacion_codificacion = pc.id_puntuacion_codificacion JOIN codificacion c ON pc.id_codificacion = c.id_codificacion WHERE pa.id_ados = ?', [$id_ados]);

        // Calculos por modulo
        $nacimiento = new Carbon($datos['fecha_nacimiento']);
        $fechaTest = new Carbon($datos['fecha']);

        // Buscar puntaje A1 si existe
        $puntajeA1 = null;
        foreach ($puntuaciones as $p) {
            if (isset($p->codigo) && $p->codigo === 'A1') {
                $puntajeA1 = $p->puntaje;
                break;
            }
        }

        // Determinar algoritmo según modulo
        $id_algoritmo = null;
        if ($moduloEsperado === 'T') {
            $meses = $fechaTest->diffInMonths($nacimiento);
            if (($meses >= 12 && $meses <= 20) || ($meses >= 21 && $meses <= 30 && ($puntajeA1 === 3 || $puntajeA1 === 4))) {
                $id_algoritmo = 7;
            } elseif ($meses >= 21 && $meses <= 30 && in_array($puntajeA1, [0, 1, 2])) {
                $id_algoritmo = 8;
            }
        } elseif ($moduloEsperado === '1') {
            if ($puntajeA1 === 3 || $puntajeA1 === 4)
                $id_algoritmo = 1;
            else
                $id_algoritmo = 2;
        } elseif ($moduloEsperado === '3') {
            $id_algoritmo = 5;
        } elseif ($moduloEsperado === '2') {
            $edad = $fechaTest->diffInYears($nacimiento);
            $id_algoritmo = $edad < 5 ? 3 : 4;
        } elseif ($moduloEsperado === '4') {
            $id_algoritmo = 6;
        }

        // Observaciones
        $observaciones = DB::select('SELECT ar.observacion, a.nombre_actividad FROM actividad_realizada ar JOIN actividad a ON ar.id_actividad = a.id_actividad WHERE ar.id_ados = ?', [$id_ados]);

        return response()->json(array_merge($datos, ['puntuaciones' => $puntuaciones, 'observaciones' => $observaciones, 'id_algoritmo' => $id_algoritmo]));
    }
}
