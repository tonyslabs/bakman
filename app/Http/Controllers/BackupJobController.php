<?php

namespace App\Http\Controllers;

use App\Jobs\RunBackupJob;
use App\Models\BackupJob;
use App\Models\DatabaseConnection;
use App\Models\Target;
use Illuminate\Http\Request;

class BackupJobController extends Controller
{
    public function index()
    {
        $jobs = BackupJob::with(['target', 'databaseConnection', 'runs' => fn ($q) => $q->limit(1)])->orderBy('name')->get();

        return view('backup-jobs.index', ['jobs' => $jobs]);
    }

    public function create()
    {
        return view('backup-jobs.form', [
            'job' => new BackupJob(),
            'targets' => Target::orderBy('name')->get(),
            'connections' => DatabaseConnection::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        BackupJob::create($data);

        return redirect()->route('backup-jobs.index')->with('status', 'Job creado.');
    }

    public function edit(BackupJob $backupJob)
    {
        return view('backup-jobs.form', [
            'job' => $backupJob,
            'targets' => Target::orderBy('name')->get(),
            'connections' => DatabaseConnection::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, BackupJob $backupJob)
    {
        $data = $this->validated($request);
        $backupJob->update($data);

        return redirect()->route('backup-jobs.index')->with('status', 'Job actualizado.');
    }

    public function destroy(BackupJob $backupJob)
    {
        $backupJob->delete();

        return redirect()->route('backup-jobs.index')->with('status', 'Job eliminado.');
    }

    public function run(BackupJob $backupJob)
    {
        RunBackupJob::dispatch($backupJob);

        return redirect()->back(fallback: route('backup-jobs.index'))->with('status', 'Job encolado para ejecutarse.');
    }

    public function runs(BackupJob $backupJob)
    {
        return view('backup-jobs.runs', [
            'job' => $backupJob,
            'runs' => $backupJob->runs()->paginate(20),
        ]);
    }

    private function validated(Request $request): array
    {
        $type = $request->input('type');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:file,system,database,script'],
            'schedule_cron' => ['required', 'string', 'max:255'],
            'retention_count' => ['nullable', 'integer', 'min:1'],
            'enabled' => ['sometimes', 'boolean'],
        ];

        if (in_array($type, ['file', 'system'], true)) {
            $rules['target_id'] = ['required', 'exists:targets,id'];
            $rules['paths_text'] = ['required', 'string'];
        } elseif ($type === 'script') {
            $rules['target_id'] = ['required', 'exists:targets,id'];
            $rules['command'] = ['required', 'string', 'max:2000'];
            $rules['timeout_seconds'] = ['nullable', 'integer', 'min:10', 'max:'.config('backups.script_max_timeout')];
        } elseif ($type === 'database') {
            $rules['database_connection_id'] = ['required', 'exists:database_connections,id'];
            $rules['db_name'] = ['required', 'string', 'max:255'];
            $rules['tables_text'] = ['nullable', 'string'];
            $rules['schema_only'] = ['sometimes', 'boolean'];
        }

        $validated = $request->validate($rules);
        $validated['enabled'] = $request->boolean('enabled');

        if (isset($validated['paths_text'])) {
            $validated['paths'] = collect(explode("\n", $validated['paths_text']))
                ->map(fn ($p) => trim($p))
                ->filter()
                ->values()
                ->all();
            unset($validated['paths_text']);
        }

        if ($type === 'database') {
            $validated['schema_only'] = $request->boolean('schema_only');
            $validated['tables'] = collect(explode("\n", $validated['tables_text'] ?? ''))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->values()
                ->all();
            unset($validated['tables_text']);
        }

        return $validated;
    }
}
