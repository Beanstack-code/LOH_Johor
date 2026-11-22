<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center rounded-xl border border-[#dfe1ec] bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-600 shadow-sm transition hover:bg-[#f8f9fd] focus:outline-none focus:ring-2 focus:ring-[#0d4f8b] focus:ring-offset-2 disabled:opacity-25']) }}>
    {{ $slot }}
</button>
