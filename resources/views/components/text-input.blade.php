@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-xl border border-[#dfe1ec] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[#ed1c24] focus:ring-[#ed1c24]']) }}>
