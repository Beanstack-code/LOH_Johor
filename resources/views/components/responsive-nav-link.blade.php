@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-lg bg-[#fff5cf] px-4 py-2 text-start text-base font-medium text-[#9a7300] transition'
            : 'block w-full rounded-lg px-4 py-2 text-start text-base font-medium text-slate-600 transition hover:bg-[#f5f6fa] hover:text-[#2f3349]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
