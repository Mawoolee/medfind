@props([
    'href',
    'label' => 'Back',
])

<a href="{{ $href }}"
    {{ $attributes->merge(['aria-label' => $label])->class([
        'inline-flex min-h-11 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-[#9400D3] transition-colors',
        'hover:bg-[#9400D3]/10 hover:text-[#7a00b0]',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9400D3] focus-visible:ring-offset-2',
    ]) }}>
    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.56l3.22 3.22a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06l4.5-4.5a.75.75 0 0 1 1.06 1.06L5.56 9.25h10.69A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
    </svg>
    <span>Back</span>
</a>
