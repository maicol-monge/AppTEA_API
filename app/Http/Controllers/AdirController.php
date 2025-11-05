<?php

namespace App\Http\Controllers;

use App\Models\TestAdir;
use Illuminate\Http\Request;

class AdirController extends Controller
{
    public function index()
    {
        return response()->json(TestAdir::with(['paciente', 'especialista'])->get());
    }

    public function show($id)
    {
        $item = TestAdir::with(['paciente', 'especialista'])->findOrFail($id);
        return response()->json($item);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_paciente' => 'required|integer|exists:paciente,id_paciente',
            'fecha' => 'required|date',
            'algoritmo' => 'required|string',
            'tipo_sujeto' => 'required|string',
            'estado' => 'required|integer',
        ]);

        $item = TestAdir::create($data);
        return response()->json($item, 201);
    }

    public function update(Request $request, $id)
    {
        $item = TestAdir::findOrFail($id);
        $data = $request->only(['diagnostico', 'estado']);
        $item->update($data);
        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = TestAdir::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
