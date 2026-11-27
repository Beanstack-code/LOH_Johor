@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-xl border border-[#dfe1ec] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[#9a7300] focus:ring-[#9a7300]']) }}>
