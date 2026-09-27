<?php

namespace App\Http\Controllers;

use App\Jobs\RunDatabaseDiffSync;
use App\Models\DatabaseDiffSync;
use App\Services\DatabaseDiffService;
use Illuminate\Http\Request;

class DatabaseDiffSyncController extends Controller
{
    public function index()
    {
        $syncs = DatabaseDiffSync::with(['connectionA', 'connectionB'])->latest('id')->paginate(20);

        return view('database-diff-syncs.index', ['syncs' => $syncs]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'connection_a_id' => ['required', 'exists:database_connections,id'],
            'db_a' => ['required', 'string', 'max:255'],
            'connection_b_id' => ['required', 'exists:database_connections,id'],
            'db_b' => ['required', 'string', 'max:255'],
            'section' => ['required', 'in:'.implode(',', DatabaseDiffService::SECTIONS)],
            'source_side' => ['required', 'in:a,b'],
        ]);

        $sync = DatabaseDiffSync::create($validated + ['status' => 'pending']);

        RunDatabaseDiffSync::dispatch($sync);

        return redirect()->route('database-diff-syncs.index')
            ->with('status', 'Sincronización encolada, va a correr en segundo plano.');
    }
}
