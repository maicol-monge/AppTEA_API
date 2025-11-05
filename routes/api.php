<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdirController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdosController;
use App\Http\Controllers\EspecialistaController;
use App\Http\Controllers\PacienteController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Minimal routes for testing purposes. POST /api/login will call AuthController@login
|
*/

Route::post('/login', [AuthController::class, 'login']);

// Admin CRUD endpoints (users, pacientes, especialistas, areas, preguntas, actividades, tests)
Route::middleware([
    \App\Http\Middleware\AuthenticateApiToken::class,
    \App\Http\Middleware\EnsureAdminPrivilege::class,
])->prefix('admin')->group(function () {
    // Usuarios
    Route::get('/usuarios', [AdminController::class, 'getUsuarios']);
    Route::post('/usuarios', [AdminController::class, 'createUsuario']);
    Route::put('/usuarios/{id_usuario}', [AdminController::class, 'updateUsuario']);
    Route::delete('/usuarios/{id_usuario}', [AdminController::class, 'deleteUsuario']);

    // Pacientes
    Route::get('/pacientes', [AdminController::class, 'getPacientes']);
    Route::post('/pacientes', [AdminController::class, 'createPaciente']);
    Route::put('/pacientes/{id_paciente}', [AdminController::class, 'updatePaciente']);
    Route::delete('/pacientes/{id_paciente}', [AdminController::class, 'deletePaciente']);

    // Especialistas
    Route::get('/especialistas', [AdminController::class, 'getEspecialistas']);
    Route::post('/especialistas', [AdminController::class, 'createEspecialista']);
    Route::put('/especialistas/{id_especialista}', [AdminController::class, 'updateEspecialista']);
    Route::delete('/especialistas/{id_especialista}', [AdminController::class, 'deleteEspecialista']);

    // Áreas
    Route::get('/areas', [AdminController::class, 'getAreas']);
    Route::post('/areas', [AdminController::class, 'createArea']);
    Route::put('/areas/{id_area}', [AdminController::class, 'updateArea']);
    Route::delete('/areas/{id_area}', [AdminController::class, 'deleteArea']);

    // Preguntas ADI
    Route::get('/preguntas', [AdminController::class, 'getPreguntas']);
    Route::post('/preguntas', [AdminController::class, 'createPregunta']);
    Route::put('/preguntas/{id_pregunta}', [AdminController::class, 'updatePregunta']);
    Route::delete('/preguntas/{id_pregunta}', [AdminController::class, 'deletePregunta']);

    // Actividades ADOS
    Route::get('/actividades', [AdminController::class, 'getActividades']);
    Route::post('/actividades', [AdminController::class, 'createActividad']);
    Route::put('/actividades/{id_actividad}', [AdminController::class, 'updateActividad']);
    Route::delete('/actividades/{id_actividad}', [AdminController::class, 'deleteActividad']);

    // Tests ADI-R
    Route::get('/tests-adir', [AdminController::class, 'getTestsAdiR']);
    Route::put('/tests-adir/{id_adir}', [AdminController::class, 'updateTestAdiR']);
    Route::delete('/tests-adir/{id_adir}', [AdminController::class, 'deleteTestAdiR']);

    // Tests ADOS-2
    Route::get('/tests-ados', [AdminController::class, 'getTestsAdos2']);
    Route::put('/tests-ados/{id_ados}', [AdminController::class, 'updateTestAdos2']);
    Route::delete('/tests-ados/{id_ados}', [AdminController::class, 'deleteTestAdos2']);
});

// ADI-R endpoints (match Node routes in adirRoutes.js)
Route::get('/adir/listar/{id_paciente}', [AdirController::class, 'listarTestsPorPaciente']);
Route::get('/adir/resumen/{id_adir}', [AdirController::class, 'obtenerResumenEvaluacion']);
Route::put('/adir/diagnostico/{id_adir}', [AdirController::class, 'guardarDiagnostico']);
Route::get('/adir/resumen-ultimo/{id_paciente}', [AdirController::class, 'resumenUltimoTestPorPaciente']);
Route::get('/adir/listar-con-diagnostico/{id_paciente}', [AdirController::class, 'listarTestsConDiagnosticoPorPaciente']);
Route::get('/adir/pdf/{id_adir}', [AdirController::class, 'generarPdfAdir']);
Route::get('/adir/preguntas', [AdirController::class, 'obtenerPreguntasAdir']);
Route::post('/adir/crear-test', [AdirController::class, 'crearTestAdir']);
Route::get('/adir/preguntas-con-respuestas/{id_adir}', [AdirController::class, 'obtenerPreguntasConRespuestas']);
Route::get('/adir/codigos-por-pregunta', [AdirController::class, 'obtenerCodigosPorPregunta']);
Route::post('/adir/guardar-respuesta', [AdirController::class, 'guardarRespuestaAdir']);
Route::get('/adir/id-paciente/{id_adir}', [AdirController::class, 'obtenerIdPacientePorAdir']);
Route::put('/adir/determinar-tipo-sujeto/{id_adir}', [AdirController::class, 'determinarYActualizarTipoSujeto']);
Route::get('/adir/fecha-entrevista/{id_adir}', [AdirController::class, 'obtenerFechaEntrevistaPorAdir']);
Route::put('/adir/guardar-diagnostico-final/{id_adir}', [AdirController::class, 'guardarDiagnosticoFinal']);
Route::get('/adir/resumen-paciente/{id_adir}', [AdirController::class, 'obtenerResumenPacienteAdir']);

// ADOS-2 endpoints
Route::get('/ados/pacientes', [AdosController::class, 'listarPacientesConAdos']);
Route::get('/ados/tests/{id_paciente}', [AdosController::class, 'listarTestsAdosPorPaciente']);
Route::get('/ados/actividades/{modulo}', [AdosController::class, 'listarActividadesPorModulo']);
Route::post('/ados/crear', [AdosController::class, 'crearTestAdos']);
Route::post('/ados/actividad-realizada', [AdosController::class, 'guardarActividadRealizada']);
Route::put('/ados/pausar/{id_ados}', [AdosController::class, 'pausarTestAdos']);
Route::get('/ados/test-pausado', [AdosController::class, 'buscarTestPausado']);
Route::get('/ados/actividades-realizadas/{id_ados}', [AdosController::class, 'obtenerActividadesRealizadas']);
Route::post('/ados/responder-item', [AdosController::class, 'responderItem']);
Route::get('/ados/codificaciones-algoritmo/{id_algoritmo}', [AdosController::class, 'codificacionesPorAlgoritmo']);
Route::get('/ados/puntuaciones-codificacion/{id_codificacion}', [AdosController::class, 'puntuacionesPorCodificacion']);
Route::post('/ados/responder-codificacion', [AdosController::class, 'responderCodificacion']);
Route::get('/ados/paciente/{id_paciente}', [AdosController::class, 'obtenerPacientePorId']);
Route::get('/ados/codificacion/{id_codificacion}', [AdosController::class, 'codificacionPorId']);
Route::get('/ados/algoritmo/{id_algoritmo}', [AdosController::class, 'obtenerAlgoritmoPorId']);
Route::get('/ados/respuestas-algoritmo/{id_ados}', [AdosController::class, 'respuestasAlgoritmo']);
Route::get('/ados/test/{id_ados}', [AdosController::class, 'obtenerTestPorId']);
Route::get('/ados/algoritmo-por-test/{id_ados}', [AdosController::class, 'obtenerAlgoritmoPorTest']);
Route::get('/ados/puntuaciones-aplicadas/{id_ados}', [AdosController::class, 'puntuacionesAplicadasPorTest']);
Route::put('/ados/clasificacion/{id_ados}', [AdosController::class, 'actualizarClasificacion']);
Route::put('/ados/puntuacion-comparativa/{id_ados}', [AdosController::class, 'actualizarPuntuacionComparativa']);
Route::put('/ados/diagnostico/{id_ados}', [AdosController::class, 'actualizarDiagnostico']);
Route::get('/ados/actividades-por-test/{id_ados}', [AdosController::class, 'obtenerActividadesPorTest']);
Route::get('/ados/grupo-codificacion/{id_codificacion}', [AdosController::class, 'obtenerGrupoPorCodificacion']);
Route::get('/ados/reporte-modulo-t/{id_ados}', [AdosController::class, 'obtenerDatosReporteModuloT']);
Route::get('/ados/reporte-modulo-1/{id_ados}', [AdosController::class, 'obtenerDatosReporteModulo1']);
Route::get('/ados/reporte-modulo-3/{id_ados}', [AdosController::class, 'obtenerDatosReporteModulo3']);
Route::get('/ados/reporte-modulo-2/{id_ados}', [AdosController::class, 'obtenerDatosReporteModulo2']);
Route::get('/ados/reporte-modulo-4/{id_ados}', [AdosController::class, 'obtenerDatosReporteModulo4']);
Route::get('/ados/validar-filtros/{id_paciente}', [AdosController::class, 'validarFiltrosPaciente']);

// Especialista routes (match Node especialistaRoutes.js) - protected by token middleware
Route::middleware([\App\Http\Middleware\AuthenticateApiToken::class])->group(function () {
    Route::get('/especialista/buscar-espe/{id_usuario}', [EspecialistaController::class, 'buscarEspecialistaPorUsuario']);
    Route::post('/especialista/aceptar-consentimiento', [EspecialistaController::class, 'aceptarConsentimientoEspecialista']);
    Route::get('/especialista/reportes/pacientes-con-tests', [EspecialistaController::class, 'pacientesConTests']);
});

// Paciente routes (match Node pacienteRoutes.js) - protected by token middleware
Route::middleware([\App\Http\Middleware\AuthenticateApiToken::class])->group(function () {
    Route::get('/paciente/buscar-paciente/{id_usuario}', [PacienteController::class, 'buscarPacientePorUsuario']);
    Route::post('/paciente/aceptar-consentimiento', [PacienteController::class, 'aceptarConsentimiento']);
    Route::post('/paciente/guardar-dsm5', [PacienteController::class, 'guardarDsm5']);
    Route::get('/paciente/validar-terminos/{id_usuario}', [PacienteController::class, 'validarTerminos']);
    Route::put('/paciente/desactivar/{id_usuario}', [PacienteController::class, 'desactivarCuenta']);
    Route::get('/paciente/resultados/{id_paciente}', [PacienteController::class, 'listarResultadosPaciente']);
});

// User routes (match Node userRoutes.js)
Route::post('/login', [AuthController::class, 'login']); // already present above but keep for parity

Route::middleware([\App\Http\Middleware\AuthenticateApiToken::class])->group(function () {
    Route::post('/registrar', [AuthController::class, 'registrar']);
    Route::post('/cambiar-contrasena', [AuthController::class, 'cambiarContrasena']);
    Route::get('/pacientes', [AuthController::class, 'listarPacientes']);
    Route::put('/cambiar-password', [AuthController::class, 'cambiarPasswordConActual']);
});

Route::post('/recuperar-contrasena', [AuthController::class, 'recuperarContrasena']);

// Compatibility routes matching the original Node backend under /api/users
Route::prefix('users')->group(function () {
    // Public
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/recuperar-contrasena', [AuthController::class, 'recuperarContrasena']);

    // Protected (require token) - mirror Node behaviour
    Route::post('/registrar', [AuthController::class, 'registrar'])->middleware(\App\Http\Middleware\AuthenticateApiToken::class);
    Route::post('/cambiar-contrasena', [AuthController::class, 'cambiarContrasena'])->middleware(\App\Http\Middleware\AuthenticateApiToken::class);
    Route::get('/pacientes', [AuthController::class, 'listarPacientes'])->middleware(\App\Http\Middleware\AuthenticateApiToken::class);
    Route::put('/cambiar-password', [AuthController::class, 'cambiarPasswordConActual'])->middleware(\App\Http\Middleware\AuthenticateApiToken::class);
});

// Dev-only helper to bootstrap an admin user locally
if (app()->environment('local')) {
    Route::post('/dev/bootstrap-admin', [AuthController::class, 'bootstrapAdmin']);
    Route::post('/users/dev/bootstrap-admin', [AuthController::class, 'bootstrapAdmin']);
}
