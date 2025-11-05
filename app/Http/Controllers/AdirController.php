<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdirController extends Controller
{
    // Listar tests ADIR por paciente
    public function listarTestsPorPaciente($id_paciente)
    {
        $query = "SELECT t.id_adir, t.fecha, t.diagnostico, t.estado FROM test_adi_r t WHERE t.id_paciente = ? ORDER BY t.fecha DESC";
        $results = DB::select($query, [$id_paciente]);
        return response()->json($results);
    }

    // Listar tests ADIR por paciente SOLO con diagnóstico
    public function listarTestsConDiagnosticoPorPaciente($id_paciente)
    {
        $query = "SELECT t.id_adir, t.fecha, t.diagnostico FROM test_adi_r t WHERE t.id_paciente = ? AND t.diagnostico IS NOT NULL ORDER BY t.fecha DESC";
        $results = DB::select($query, [$id_paciente]);
        return response()->json($results);
    }

    // Obtener resumen de un test ADIR
    public function obtenerResumenEvaluacion($id_adir)
    {
        $testQuery = <<<SQL
SELECT
    t.id_adir,
    t.fecha AS fecha_entrevista,
    t.diagnostico,
    t.algoritmo,
    t.tipo_sujeto,
    t.estado,
    t.id_especialista,
    p.id_paciente,
    u.nombres,
    u.apellidos,
    p.sexo,
    p.fecha_nacimiento,
    ue.nombres AS especialista_nombre,
    ue.apellidos AS especialista_apellidos
FROM test_adi_r t
JOIN paciente p ON t.id_paciente = p.id_paciente
JOIN usuario u ON p.id_usuario = u.id_usuario
LEFT JOIN especialista e ON t.id_especialista = e.id_especialista
LEFT JOIN usuario ue ON e.id_usuario = ue.id_usuario
WHERE t.id_adir = ?
SQL;

        $testResults = DB::select($testQuery, [$id_adir]);
        if (empty($testResults)) {
            return response()->json(['message' => 'Test no encontrado.'], 404);
        }

        $respuestasQuery = <<<SQL
SELECT r.id_pregunta, a.area, q.pregunta, r.codigo, r.observacion
FROM respuesta_adi r
JOIN pregunta_adi q ON r.id_pregunta = q.id_pregunta
JOIN area a ON q.id_area = a.id_area
WHERE r.id_adir = ?
SQL;

        $respuestas = DB::select($respuestasQuery, [$id_adir]);

        $test = (array) $testResults[0];
        $test['especialista'] = ($test['especialista_nombre'] ?? '') ? trim(($test['especialista_nombre'] ?? '') . ' ' . ($test['especialista_apellidos'] ?? '')) : '';
        unset($test['especialista_nombre'], $test['especialista_apellidos']);

        return response()->json(['test' => $test, 'respuestas' => $respuestas]);
    }

    // Guardar diagnóstico
    public function guardarDiagnostico(Request $request, $id_adir)
    {
        $data = $request->validate([
            'diagnostico' => 'nullable|string',
            'id_especialista' => 'nullable|integer',
        ]);

        DB::update('UPDATE test_adi_r SET diagnostico = ?, id_especialista = ? WHERE id_adir = ?', [$data['diagnostico'] ?? null, $data['id_especialista'] ?? null, $id_adir]);

        // Buscar datos del paciente para enviar el correo
        $pacienteQuery = <<<SQL
SELECT u.correo, u.nombres, u.apellidos
FROM test_adi_r t
JOIN paciente p ON t.id_paciente = p.id_paciente
JOIN usuario u ON p.id_usuario = u.id_usuario
WHERE t.id_adir = ?
SQL;

        $rows = DB::select($pacienteQuery, [$id_adir]);
        if (!empty($rows)) {
            $row = (array) $rows[0];
            $this->enviarCorreoDiagnostico($row['correo'], $row['nombres'], $row['apellidos'], $data['diagnostico'] ?? null);
        }

        return response()->json(['message' => 'Diagnóstico guardado correctamente.']);
    }

    // Resumen del último test por paciente (preguntas+respuestas)
    public function resumenUltimoTestPorPaciente($id_paciente)
    {
        $testQuery = "SELECT t.id_adir, t.fecha, t.diagnostico, p.id_paciente, u.nombres, u.apellidos, p.sexo, p.fecha_nacimiento FROM test_adi_r t JOIN paciente p ON t.id_paciente = p.id_paciente JOIN usuario u ON p.id_usuario = u.id_usuario WHERE t.id_paciente = ? ORDER BY t.fecha DESC LIMIT 1";
        $testResults = DB::select($testQuery, [$id_paciente]);
        if (empty($testResults))
            return response()->json(['message' => 'El paciente no tiene tests ADIR.'], 404);
        $test = (array) $testResults[0];

        $respuestasQuery = "SELECT r.id_pregunta, q.pregunta, r.calificacion, r.observacion FROM respuesta_adi r JOIN pregunta_adi q ON r.id_pregunta = q.id_pregunta WHERE r.id_adir = ?";
        $respuestas = DB::select($respuestasQuery, [$test['id_adir']]);

        return response()->json(['test' => $test, 'respuestas' => $respuestas]);
    }

    // Generar PDF de resultados ADI-R (usa Dompdf si está instalado, si no devuelve JSON con datos)
    public function generarPdfAdir($id_adir)
    {
        // Obtener test
        $adirRows = DB::select('SELECT * FROM test_adi_r WHERE id_adir = ?', [$id_adir]);
        if (empty($adirRows))
            return response()->json(['message' => 'No existe el test.'], 404);
        $adir = (array) $adirRows[0];

        // paciente
        $pacienteRows = DB::select('SELECT * FROM paciente WHERE id_paciente = ?', [$adir['id_paciente']]);
        if (empty($pacienteRows))
            return response()->json(['message' => 'Paciente no encontrado.'], 404);
        $paciente = (array) $pacienteRows[0];

        // usuario paciente
        $usuarioPacienteRows = DB::select('SELECT * FROM usuario WHERE id_usuario = ?', [$paciente['id_usuario']]);
        if (empty($usuarioPacienteRows))
            return response()->json(['message' => 'Usuario paciente no encontrado.'], 404);
        $usuarioPaciente = (array) $usuarioPacienteRows[0];

        // especialista
        $especialistaRows = DB::select('SELECT * FROM especialista WHERE id_especialista = ?', [$adir['id_especialista'] ?? null]);
        $especialista = !empty($especialistaRows) ? (array) $especialistaRows[0] : [];
        $usuarioEspecialista = [];
        if (!empty($especialista)) {
            $usr = DB::select('SELECT * FROM usuario WHERE id_usuario = ?', [$especialista['id_usuario'] ?? null]);
            $usuarioEspecialista = !empty($usr) ? (array) $usr[0] : ['nombres' => '', 'apellidos' => ''];
        }

        $respuestas = DB::select("SELECT r.*, p.pregunta FROM respuesta_adi r JOIN pregunta_adi p ON p.id_pregunta = r.id_pregunta WHERE r.id_adir = ?", [$id_adir]);

        // If Dompdf available, render PDF
        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $html = '<h3>ADI-R Report</h3>';
                $html .= '<p><strong>Nombre:</strong> ' . e($usuarioPaciente['nombres'] . ' ' . $usuarioPaciente['apellidos']) . '</p>';
                $html .= '<p><strong>ID Paciente:</strong> ' . e($paciente['id_paciente']) . '</p>';
                $html .= '<p><strong>Diagnóstico:</strong> ' . e($adir['diagnostico'] ?? 'Pendiente') . '</p>';
                $html .= '<hr/>';
                foreach ($respuestas as $r) {
                    $html .= '<p><strong>' . e($r->pregunta) . '</strong><br/>Calificación: ' . e($r->calificacion ?? $r->codigo) . '<br/>Obs: ' . e($r->observacion) . '</p>';
                }

                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                return response($dompdf->output(), 200)->header('Content-Type', 'application/pdf');
            } catch (\Throwable $e) {
                Log::error('PDF generation error: ' . $e->getMessage());
                return response()->json(['message' => 'Error generando PDF.'], 500);
            }
        }

        // Fallback: return the data if PDF lib not installed
        return response()->json([
            'adir' => $adir,
            'paciente' => $paciente,
            'usuarioPaciente' => $usuarioPaciente,
            'usuarioEspecialista' => $usuarioEspecialista,
            'respuestas' => $respuestas,
            'note' => 'Instala dompdf/dompdf (composer require dompdf/dompdf) to enable PDF generation.'
        ]);
    }

    // Obtener todas las preguntas ADI-R
    public function obtenerPreguntasAdir()
    {
        $results = DB::select('SELECT id_pregunta, pregunta FROM pregunta_adi ORDER BY id_pregunta');
        return response()->json($results);
    }

    // Crear un nuevo test_adi_r (simple)
    public function crearTestAdir(Request $request)
    {
        $id_paciente = $request->input('id_paciente');
        $fecha = Carbon::now();
        $id = DB::table('test_adi_r')->insertGetId(['id_paciente' => $id_paciente, 'fecha' => $fecha]);
        return response()->json(['id_adir' => $id]);
    }

    // Guardar respuestas ADIR
    public function guardarRespuestasAdir(Request $request, $id_adir)
    {
        $respuestas = $request->input('respuestas', []);
        $observaciones = $request->input('observaciones', []);

        $values = [];
        foreach ($respuestas as $id_pregunta => $valor) {
            $values[] = ['id_adir' => $id_adir, 'id_pregunta' => $id_pregunta, 'calificacion' => $valor, 'observacion' => $observaciones[$id_pregunta] ?? ''];
        }

        if (!empty($values)) {
            foreach ($values as $v) {
                DB::table('respuesta_adi')->insert($v);
            }
        }

        // enviar correo si hay paciente
        $pacienteQuery = "SELECT u.correo, u.nombres, u.apellidos FROM test_adi_r t JOIN paciente p ON t.id_paciente = p.id_paciente JOIN usuario u ON p.id_usuario = u.id_usuario WHERE t.id_adir = ?";
        $rows = DB::select($pacienteQuery, [$id_adir]);
        if (!empty($rows)) {
            $r = (array) $rows[0];
            $this->enviarCorreoEvaluacionEnviada($r['correo'], $r['nombres'], $r['apellidos']);
        }

        return response()->json(['message' => 'Respuestas guardadas correctamente.']);
    }

    // Obtener preguntas y respuestas previas para un test (incluye paciente)
    public function obtenerPreguntasConRespuestas($id_adir)
    {
        $rows = DB::select(
            "SELECT p.id_pregunta, p.pregunta, p.id_area, a.area, r.codigo as codigo_respuesta, r.observacion FROM pregunta_adi p JOIN area a ON p.id_area = a.id_area LEFT JOIN respuesta_adi r ON r.id_pregunta = p.id_pregunta AND r.id_adir = ? ORDER BY a.id_area, p.id_pregunta",
            [$id_adir]
        );

        $preguntas = [];
        $respuestas = [];
        foreach ($rows as $row) {
            $preguntas[] = $row;
            if ($row->codigo_respuesta !== null) {
                $respuestas[$row->id_pregunta] = ['codigo' => $row->codigo_respuesta, 'observacion' => $row->observacion];
            }
        }

        $pacienteRows = DB::select("SELECT p.id_paciente, u.nombres, u.apellidos, p.sexo, p.fecha_nacimiento FROM test_adi_r t JOIN paciente p ON t.id_paciente = p.id_paciente JOIN usuario u ON p.id_usuario = u.id_usuario WHERE t.id_adir = ?", [$id_adir]);
        if (empty($pacienteRows)) {
            return response()->json(['preguntas' => $preguntas, 'respuestas' => $respuestas]);
        }

        return response()->json(['preguntas' => $preguntas, 'respuestas' => $respuestas, 'paciente' => $pacienteRows[0]]);
    }

    // Devuelve los códigos válidos para cada pregunta
    public function obtenerCodigosPorPregunta()
    {
        $rows = DB::select('SELECT c.id_codigo, c.codigo, c.id_pregunta FROM codigo c');
        $codigosPorPregunta = [];
        foreach ($rows as $r) {
            if (!isset($codigosPorPregunta[$r->id_pregunta]))
                $codigosPorPregunta[$r->id_pregunta] = [];
            $codigosPorPregunta[$r->id_pregunta][] = ['id_codigo' => $r->id_codigo, 'codigo' => $r->codigo];
        }
        return response()->json($codigosPorPregunta);
    }

    // Guardar o actualizar respuesta singular
    public function guardarRespuestaAdir(Request $request)
    {
        $data = $request->validate([
            'id_adir' => 'required|integer',
            'id_pregunta' => 'required|integer',
            'codigo' => 'nullable',
            'observacion' => 'nullable|string',
        ]);

        $exists = DB::select('SELECT id_respuesta FROM respuesta_adi WHERE id_adir = ? AND id_pregunta = ?', [$data['id_adir'], $data['id_pregunta']]);
        if (!empty($exists)) {
            DB::update('UPDATE respuesta_adi SET codigo = ?, observacion = ? WHERE id_adir = ? AND id_pregunta = ?', [$data['codigo'] ?? null, $data['observacion'] ?? null, $data['id_adir'], $data['id_pregunta']]);
            return response()->json(['message' => 'Respuesta actualizada.']);
        }

        DB::insert('INSERT INTO respuesta_adi (id_adir, id_pregunta, codigo, observacion) VALUES (?, ?, ?, ?)', [$data['id_adir'], $data['id_pregunta'], $data['codigo'] ?? null, $data['observacion'] ?? null]);
        return response()->json(['message' => 'Respuesta guardada.']);
    }

    // Obtener solo el id_paciente a partir de un id_adir
    public function obtenerIdPacientePorAdir($id_adir)
    {
        $rows = DB::select('SELECT id_paciente FROM test_adi_r WHERE id_adir = ?', [$id_adir]);
        if (empty($rows))
            return response()->json(['message' => 'Test no encontrado.'], 404);
        return response()->json(['id_paciente' => $rows[0]->id_paciente]);
    }

    // Determinar y actualizar tipo de sujeto en test_adi_r
    public function determinarYActualizarTipoSujeto(Request $request, $id_adir)
    {
        $rows = DB::select('SELECT codigo FROM respuesta_adi WHERE id_adir = ? AND id_pregunta = 30 LIMIT 1', [$id_adir]);
        if (empty($rows))
            return response()->json(['message' => 'No existe respuesta para la pregunta 30.'], 404);
        $codigo = intval($rows[0]->codigo);
        $tipo_sujeto = 'no-verbal';
        if ($codigo === 0)
            $tipo_sujeto = 'verbal';
        DB::update('UPDATE test_adi_r SET tipo_sujeto = ? WHERE id_adir = ?', [$tipo_sujeto, $id_adir]);
        return response()->json(['tipo_sujeto' => $tipo_sujeto]);
    }

    // Obtener la fecha de la entrevista (fecha del test) por id_adir
    public function obtenerFechaEntrevistaPorAdir($id_adir)
    {
        $rows = DB::select('SELECT fecha FROM test_adi_r WHERE id_adir = ?', [$id_adir]);
        if (empty($rows))
            return response()->json(['message' => 'Test no encontrado.'], 404);
        return response()->json(['fecha_entrevista' => $rows[0]->fecha]);
    }

    // Guardar o actualizar algoritmo, diagnóstico y estado del test_adi_r
    public function guardarDiagnosticoFinal(Request $request, $id_adir)
    {
        $data = $request->validate([
            'algoritmo' => 'nullable',
            'diagnostico' => 'nullable|string',
            'estado' => 'nullable|integer',
        ]);

        DB::update('UPDATE test_adi_r SET algoritmo = ?, diagnostico = ?, estado = ? WHERE id_adir = ?', [$data['algoritmo'] ?? null, $data['diagnostico'] ?? null, $data['estado'] ?? null, $id_adir]);

        $rows = DB::select('SELECT u.correo, u.nombres, u.apellidos FROM test_adi_r t JOIN paciente p ON t.id_paciente = p.id_paciente JOIN usuario u ON p.id_usuario = u.id_usuario WHERE t.id_adir = ?', [$id_adir]);
        if (!empty($rows)) {
            $r = (array) $rows[0];
            $this->enviarCorreoDiagnosticoFinalADI($r['correo'], $r['nombres'], $r['apellidos']);
        }

        return response()->json(['message' => 'Diagnóstico final guardado correctamente.']);
    }

    private function enviarCorreoDiagnosticoFinalADI($destinatario, $nombre, $apellidos)
    {
        try {
            Mail::raw("Hola $nombre $apellidos,\n\nTe informamos que el diagnóstico de tu test ADI-R ha sido actualizado por el especialista.\n\nYa puedes consultar el resultado desde la sección de \"Resultados\" en el sistema TEA Diagnóstico.\n\nSaludos,\nEquipo TEA Diagnóstico", function ($message) use ($destinatario) {
                $message->to($destinatario)->subject('Diagnóstico actualizado - Test ADI-R');
            });
        } catch (\Throwable $e) {
            Log::error('Error enviando correo diagnóstico final ADIR: ' . $e->getMessage());
        }
    }

    // Obtener resumen paciente ADI-R (datos personales + respuestas agrupadas)
    public function obtenerResumenPacienteAdir($id_adir)
    {
        $rows = DB::select(
            "SELECT t.id_adir, t.fecha AS fecha_entrevista, t.diagnostico, t.algoritmo, t.tipo_sujeto, t.estado, u.nombres, u.apellidos, ue.nombres AS especialista_nombre, ue.apellidos AS especialista_apellidos FROM test_adi_r t JOIN paciente p ON t.id_paciente = p.id_paciente JOIN usuario u ON p.id_usuario = u.id_usuario LEFT JOIN especialista e ON t.id_especialista = e.id_especialista LEFT JOIN usuario ue ON e.id_usuario = ue.id_usuario WHERE t.id_adir = ?",
            [$id_adir]
        );
        if (empty($rows))
            return response()->json(['message' => 'No se encontró el test.'], 404);
        $datos = (array) $rows[0];

        $respuestas = DB::select("SELECT a.area, q.id_pregunta, q.pregunta, r.codigo, r.observacion FROM respuesta_adi r JOIN pregunta_adi q ON r.id_pregunta = q.id_pregunta JOIN area a ON q.id_area = a.id_area WHERE r.id_adir = ? ORDER BY a.id_area, q.id_pregunta", [$id_adir]);

        return response()->json([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'fecha' => $datos['fecha_entrevista'],
            'especialista' => ($datos['especialista_nombre'] ?? '') ? trim(($datos['especialista_nombre'] ?? '') . ' ' . ($datos['especialista_apellidos'] ?? '')) : '',
            'diagnostico' => $datos['diagnostico'] ?? 'Aquí aparecerá el resumen de tu diagnóstico.',
            'algoritmo' => $datos['algoritmo'] ?? 'No disponible',
            'tipo_sujeto' => $datos['tipo_sujeto'] ?? 'No disponible',
            'respuestas' => $respuestas,
        ]);
    }

    // Helper: enviar correo de diagnóstico
    private function enviarCorreoDiagnostico($destinatario, $nombre, $apellidos, $diagnostico)
    {
        try {
            Mail::raw("Hola $nombre $apellidos,\n\nSe ha registrado un nuevo diagnóstico ADIR para ti:\n\nConsulta tu diagnostico en la sección de Resultados\n\nSi tienes dudas, contacta a tu especialista.\n\nSaludos.", function ($message) use ($destinatario) {
                $message->to($destinatario)->subject('Nuevo diagnóstico ADIR');
            });
        } catch (\Throwable $e) {
            Log::error('Error enviando correo diagnostico ADIR: ' . $e->getMessage());
        }
    }

    private function enviarCorreoEvaluacionEnviada($destinatario, $nombre, $apellidos)
    {
        try {
            Mail::raw("Hola $nombre $apellidos,\n\nTu evaluación ADI-R ha sido enviada correctamente. Por favor, mantente pendiente de tus resultados. Un especialista revisará tu caso y pronto recibirás un diagnóstico.\n\nGracias por tu confianza.\n\nSaludos", function ($message) use ($destinatario) {
                $message->to($destinatario)->subject('Evaluación ADI-R enviada');
            });
        } catch (\Throwable $e) {
            Log::error('Error enviando correo evaluacion enviada: ' . $e->getMessage());
        }
    }
}

