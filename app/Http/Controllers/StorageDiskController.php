<?php

namespace App\Http\Controllers;

use App\Models\StorageDisk;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Alta, edición y baja de los discos que se pueden explorar en Almacenamiento. */
class StorageDiskController extends Controller
{
    public function create()
    {
        return view('storage-disks.form', ['disk' => new StorageDisk]);
    }

    public function store(Request $request)
    {
        $disk = StorageDisk::create($this->validated($request));

        return redirect()->route('files.show', $disk)->with('status', 'Disco agregado.');
    }

    public function edit(StorageDisk $disk)
    {
        return view('storage-disks.form', ['disk' => $disk]);
    }

    public function update(Request $request, StorageDisk $disk)
    {
        $disk->update($this->validated($request));

        return redirect()->route('files.show', $disk)->with('status', 'Disco actualizado.');
    }

    /** Solo olvida el disco en bakman: no toca ningún archivo. */
    public function destroy(StorageDisk $disk)
    {
        $disk->delete();

        return redirect()->route('files.index')->with('status', 'Disco quitado de bakman (no se tocó ningún archivo).');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'path' => ['required', 'string', 'max:255', 'starts_with:/'],
        ]);
        $data['path'] = rtrim($data['path'], '/') ?: '/';

        // Si la carpeta existe se valida ya; si todavía no está montada se acepta (se verá "sin montar").
        $real = realpath($data['path']);
        if ($real !== false && StorageDisk::isForbidden($real)) {
            throw ValidationException::withMessages(['path' => 'Esa ruta no se puede explorar (es la raíz, el código de bakman o la carpeta de la clave SSH).']);
        }

        return $data;
    }
}
