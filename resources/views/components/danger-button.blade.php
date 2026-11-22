<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-xl border border-transparent bg-[#ed1c24] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#ca111b] focus:outline-none focus:ring-2 focus:ring-[#ed1c24] focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
