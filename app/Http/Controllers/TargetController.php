<?php

namespace App\Http\Controllers;

use App\Models\Target;
use Illuminate\Http\Request;

class TargetController extends Controller
{
    public function index()
    {
        return view('targets.index', ['targets' => Target::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('targets.form', ['target' => new Target()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Target::create($data);

        return redirect()->route('targets.index')->with('status', 'Target creado.');
    }

    public function edit(Target $target)
    {
        return view('targets.form', ['target' => $target]);
    }

    public function update(Request $request, Target $target)
    {
        $data = $this->validated($request, $target->id);
        $target->update($data);

        return redirect()->route('targets.index')->with('status', 'Target actualizado.');
    }

    public function destroy(Target $target)
    {
        $target->delete();

        return redirect()->route('targets.index')->with('status', 'Target eliminado.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:targets,name,'.($ignoreId ?? 'NULL').',id'],
            'hostname' => ['required', 'string', 'max:255'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
