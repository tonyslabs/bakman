{{-- <option>s de área agrupadas por sección. $selected, $empty (texto de la opción vacía o null). --}}
@if ($empty ?? null)
    <option value="">{{ $empty }}</option>
@endif
@foreach (config('tasks.secciones') as $seccion)
    <optgroup label="{{ $seccion['label'] }}">
        @foreach ($seccion['areas'] as $key => $label)
            <option value="{{ $key }}" @selected(($selected ?? null) === $key)>{{ $label }}</option>
        @endforeach
    </optgroup>
@endforeach
