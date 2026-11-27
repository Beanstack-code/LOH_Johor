<form method="POST" action="{{ route('locale.switch') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1" aria-label="Language">
    @csrf
    <button type="submit" name="locale" value="en" class="rounded-md px-2 py-1 text-xs font-semibold transition {{ app()->getLocale() === 'en' ? 'bg-amber-100 text-amber-900' : 'text-slate-500 hover:bg-slate-50' }}" lang="en">EN</button>
    <button type="submit" name="locale" value="zh_CN" class="rounded-md px-2 py-1 text-xs font-semibold transition {{ app()->getLocale() === 'zh_CN' ? 'bg-amber-100 text-amber-900' : 'text-slate-500 hover:bg-slate-50' }}" lang="zh-CN">中文</button>
</form>
