<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-xl bg-[#9a7300] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-[#795b00] focus:outline-none focus:ring-2 focus:ring-[#9a7300] focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
