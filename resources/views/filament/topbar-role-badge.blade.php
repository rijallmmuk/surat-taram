@if (auth()->check())
    @php
        $user = auth()->user();
        $identity = \App\Support\Filament\PanelIdentity::forUser($user);
        $badgeClass = match ($user->role) {
            'superadmin' => 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-800',
            'admin' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
            'sekretaris' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
            'wali_nagari' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
            'warga' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800',
            default => 'bg-slate-50 text-slate-700 border-slate-200',
        };
    @endphp
    <div class="hidden sm:flex flex-col items-end mr-3 text-right">
        <span class="text-xs font-bold text-slate-900 dark:text-slate-100 leading-tight max-w-[14rem] truncate" title="{{ $user->name }}">
            {{ $user->name }}
        </span>
        <span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $badgeClass }}">
            {{ $identity['roleLabel'] }}
        </span>
    </div>
@endif
