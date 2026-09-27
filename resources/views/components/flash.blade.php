@if (session('status'))
    <div {{ $attributes->merge(['class' => 'mb-4 flex items-center gap-2 rounded-md border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-400']) }}>
        <span aria-hidden="true">✓</span>{{ session('status') }}
    </div>
@endif
