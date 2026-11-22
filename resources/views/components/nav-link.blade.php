@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-lg bg-[#fdecec] px-3 py-2 text-sm font-medium leading-5 text-[#ed1c24] transition'
            : 'inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium leading-5 text-slate-500 transition hover:bg-[#f5f6fa] hover:text-[#2f3349]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
