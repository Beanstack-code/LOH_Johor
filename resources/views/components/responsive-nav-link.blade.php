@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-lg bg-[#fdecec] px-4 py-2 text-start text-base font-medium text-[#ed1c24] transition'
            : 'block w-full rounded-lg px-4 py-2 text-start text-base font-medium text-slate-600 transition hover:bg-[#f5f6fa] hover:text-[#2f3349]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
