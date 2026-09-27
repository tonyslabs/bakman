<?php

namespace App\Http\Controllers;

use App\Jobs\RunDatabaseMigration;
use App\Models\DatabaseConnection;
use App\Models\DatabaseMigration;
use Illuminate\Http\Request;

class DatabaseMigrationController extends Controller
{
    public function index()
    {
        $migrations = DatabaseMigration::with(['sourceConnection', 'targetConnection'])
            ->latest('id')->paginate(20);

        return view('database-migrations.index', ['migrations' => $migrations]);
    }

    public function create()
    {
        return view('database-migrations.form', [
            'connections' => DatabaseConnection::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_connection_id' => ['required', 'exists:database_connections,id'],
            'source_db_name' => ['required', 'string', 'max:255'],
            'target_connection_id' => ['required', 'exists:database_connections,id'],
            'target_db_name' => ['required', 'string', 'max:255'],
            'tables_text' => ['nullable', 'string'],
        ]);

        $tables = collect(explode("\n", $validated['tables_text'] ?? ''))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values()
            ->all();

        $migration = DatabaseMigration::create([
            'source_connection_id' => $validated['source_connection_id'],
            'source_db_name' => $validated['source_db_name'],
            'target_connection_id' => $validated['target_connection_id'],
            'target_db_name' => $validated['target_db_name'],
            'tables' => $tables,
            'status' => 'pending',
        ]);

        RunDatabaseMigration::dispatch($migration);

        return redirect()->route('database-migrations.index')->with('status', 'Migración encolada.');
    }
}
