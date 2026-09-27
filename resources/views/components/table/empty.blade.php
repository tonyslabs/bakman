@props(['colspan'])

<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-10 text-center text-sm text-zinc-500">
        {{ $slot }}
    </td>
</tr>
