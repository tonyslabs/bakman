<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use App\Services\DatabaseDiffService;
use Illuminate\Http\Request;

class DatabaseDiffController extends Controller
{
    public function create()
    {
        return view('database-diff.create', [
            'connections' => DatabaseConnection::orderBy('name')->get(),
        ]);
    }

    public function compare(Request $request, DatabaseDiffService $service)
    {
        $validated = $request->validate([
            'connection_a_id' => ['required', 'exists:database_connections,id'],
            'db_a' => ['required', 'string', 'max:255'],
            'connection_b_id' => ['required', 'exists:database_connections,id'],
            'db_b' => ['required', 'string', 'max:255'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['in:'.implode(',', DatabaseDiffService::SECTIONS)],
        ]);

        $connectionA = DatabaseConnection::findOrFail($validated['connection_a_id']);
        $connectionB = DatabaseConnection::findOrFail($validated['connection_b_id']);

        try {
            $result = $service->compare($connectionA, $validated['db_a'], $connectionB, $validated['db_b'], $validated['sections']);
        } catch (\Throwable $e) {
            return back()->withErrors(['compare' => 'No se pudo comparar: '.$e->getMessage()])->withInput();
        }

        return view('database-diff.result', [
            'connectionA' => $connectionA,
            'dbA' => $validated['db_a'],
            'connectionB' => $connectionB,
            'dbB' => $validated['db_b'],
            'result' => $result,
        ]);
    }
}
