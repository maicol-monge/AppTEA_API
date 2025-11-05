<?php

namespace App\Http\Controllers;

use App\Models\TestAdos;
use Illuminate\Http\Request;

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
            'id_especialista' => 'required|integer|exists:especialista,id_especialista',
            'estado' => 'required|integer',
        ]);

        $item = TestAdos::create($data);
        return response()->json($item, 201);
    }

    public function update(Request $request, $id)
    {
        $item = TestAdos::findOrFail($id);
        $data = $request->only(['diagnostico', 'total_punto', 'clasificacion', 'puntuacion_comparativa', 'estado']);
        $item->update($data);
        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = TestAdos::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
