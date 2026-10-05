<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use Illuminate\Http\Request;

class DatabaseConnectionController extends Controller
{
    public function index()
    {
        return view('database-connections.index', ['connections' => DatabaseConnection::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('database-connections.form', ['connection' => new DatabaseConnection()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DatabaseConnection::create($data);

        return redirect()->route('database-connections.index')->with('status', 'Conexión creada.');
    }

    public function edit(DatabaseConnection $databaseConnection)
    {
        return view('database-connections.form', ['connection' => $databaseConnection]);
    }

    public function update(Request $request, DatabaseConnection $databaseConnection)
    {
        $data = $this->validated($request, $databaseConnection->id);
        $databaseConnection->update($data);

        return redirect()->route('database-connections.index')->with('status', 'Conexión actualizada.');
    }

    public function destroy(DatabaseConnection $databaseConnection)
    {
        $databaseConnection->delete();

        return redirect()->route('database-connections.index')->with('status', 'Conexión eliminada.');
    }

    public function test(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:database_connections,id'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
        ]);

        $connection = isset($data['id'])
            ? DatabaseConnection::findOrFail($data['id'])
            : new DatabaseConnection();

        $connection->host = $data['host'] ?? $connection->host;
        $connection->port = $data['port'] ?? $connection->port;
        $connection->username = $data['username'] ?? $connection->username;

        if (! empty($data['password'])) {
            $connection->password = $data['password'];
        }

        if (! $connection->host || ! $connection->port || ! $connection->username) {
            return response()->json([
                'success' => false,
                'message' => 'Completa host, puerto y usuario para probar la conexión.',
            ], 422);
        }

        try {
            $connection->pdo()->query('SELECT 1');

            return response()->json(['success' => true, 'message' => 'Conexión exitosa.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function databases(DatabaseConnection $databaseConnection)
    {
        try {
            return response()->json(['success' => true, 'databases' => $databaseConnection->databaseNames()]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function env(Request $request, DatabaseConnection $databaseConnection)
    {
        $data = $request->validate([
            'framework' => ['required', 'in:'.implode(',', array_keys(DatabaseConnection::ENV_FRAMEWORKS))],
        ]);

        return response()->json(['text' => $databaseConnection->envSnippet($data['framework'])]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:database_connections,name,'.($ignoreId ?? 'NULL').',id'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($validated['password']) && $request->route('database_connection')) {
            unset($validated['password']);
        }

        return $validated;
    }
}
