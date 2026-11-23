<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }">
    <aside class="pk-sidebar fixed inset-y-0 start-0 z-40 hidden w-72 flex-col lg:flex">
        <div class="p-4">
            <a href="{{ route('dashboard') }}" class="pk-card mb-6 flex items-center gap-3 p-4" wire:navigate>
                <x-application-logo class="h-10 w-10" />
                <div>
                    <p class="text-sm font-semibold text-[#2f3349]">柔佛州羅氏公会 Portal</p>
                    <p class="text-xs text-slate-500" x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)" x-text="now.toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + now.toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true })"></p>
                </div>
            </a>
        </div>

        <div
            class="flex-1 overflow-y-auto px-4 pb-4"
            x-data
            x-init="$nextTick(() => { $el.scrollTop = parseInt(sessionStorage.getItem('sidebar-scroll') || 0) })"
            @scroll.debounce.50ms="sessionStorage.setItem('sidebar-scroll', $el.scrollTop)"
        >
            {{-- Dashboard --}}
            <div class="space-y-1">
                <a href="{{ route('dashboard') }}" class="pk-sidebar-link {{ request()->routeIs('dashboard') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12a8.25 8.25 0 1 1 16.5 0v7.5a.75.75 0 0 1-.75.75h-15a.75.75 0 0 1-.75-.75V12Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20.25v-5.25a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v5.25" />
                    </svg>
                    <span>Dashboard</span>
                </a>
            </div>

            {{-- User Management --}}
            @can('members.view')
            <div class="mt-5 space-y-1">
                <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">User Management</p>
                <a href="{{ route('admin.members') }}" class="pk-sidebar-link {{ request()->routeIs('admin.members') ? 'pk-sidebar-link-active' : '' }}" wire:navigate><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.75a6 6 0 0 0-12 0" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" /></svg>Members</a>
            </div>
            @endcan

            {{-- QR Code --}}
            @can('member-qr.view')
            <div class="mt-5 space-y-1">
                <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">QR Code</p>
                <a href="{{ route('admin.member-qr') }}" class="pk-sidebar-link {{ request()->routeIs('admin.member-qr') ? 'pk-sidebar-link-active' : '' }}" wire:navigate><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.008v.008H6.75V6.75Zm0 9.75h.008v.008H6.75v-.008Zm9.75-9.75h.008v.008h-.008V6.75Zm-3 6.75h3v3h-3v-3Z" /></svg>Member QR</a>
            </div>
            @endcan

            {{-- Engagement --}}
            @if(auth()->user()->can('announcements.view') || auth()->user()->can('notifications.view') || auth()->user()->can('platform-benefits.view') || auth()->user()->can('fundraising.view'))
            <div class="mt-5 space-y-1">
                <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Engagement</p>
                @can('announcements.view')
                <a href="{{ route('admin.announcements') }}" class="pk-sidebar-link {{ request()->routeIs('admin.announcements') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Announcements</a>
                @endcan
                @can('notifications.view')
                <a href="{{ route('admin.notifications') }}" class="pk-sidebar-link {{ request()->routeIs('admin.notifications') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Notifications</a>
                @endcan
                @can('platform-benefits.view')
                <a href="{{ route('admin.platform-benefits') }}" class="pk-sidebar-link {{ request()->routeIs('admin.platform-benefits') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Platform Benefits</a>
                @endcan
                @can('fundraising.view')
                <a href="{{ route('admin.fundraising') }}" class="pk-sidebar-link {{ request()->routeIs('admin.fundraising') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Fundraising</a>
                @endcan
            </div>
            @endif

            {{-- Settings --}}
            @if(auth()->user()->can('subscriptions.view') || auth()->user()->can('fraud_alerts.view') || auth()->user()->can('roles.view'))
            <div class="mt-5 space-y-1">
                <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Settings</p>
                @can('subscriptions.view')
                <a href="{{ route('admin.subscription-plans') }}" class="pk-sidebar-link {{ request()->routeIs('admin.subscription-plans') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Subscription Plans</a>
                @endcan
                @can('fraud_alerts.view')
                <a href="{{ route('admin.fraud-alerts') }}" class="pk-sidebar-link {{ request()->routeIs('admin.fraud-alerts') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Fraud Alerts</a>
                @endcan
                @can('roles.view')
                <a href="{{ route('admin.roles') }}" class="pk-sidebar-link {{ request()->routeIs('admin.roles') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Roles & Permissions</a>
                @endcan
            </div>
            @endif
        </div>

        <div class="p-4 border-t border-slate-200">
            <div class="pk-card p-4">
                <p class="text-sm font-semibold text-[#2f3349]">{{ auth()->user()->name }}</p>
                <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>

                <button wire:click="logout" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-[#ed1c24] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#ca111b]">
                    <svg class="me-2 h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6.75A2.25 2.25 0 0 0 4.5 5.25v13.5A2.25 2.25 0 0 0 6.75 21h6.75a2.25 2.25 0 0 0 2.25-2.25V15" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m18 15 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    Log Out
                </button>
            </div>
        </div>
    </aside>

    <div class="pk-topbar mb-4 lg:hidden">
        <div class="flex items-center justify-between">
            <button @click="open = true" class="rounded-xl border border-[#e9eaf3] p-2 text-slate-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <a href="{{ route('dashboard') }}" class="flex items-center gap-2" wire:navigate>
                <x-application-logo class="h-8 w-8" />
                <span class="text-sm font-semibold text-[#2f3349]">柔佛州羅氏公会</span>
            </a>

            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="rounded-xl border border-[#e9eaf3] bg-white px-3 py-2 text-sm font-medium text-slate-600">
                        {{ auth()->user()->name }}
                    </button>
                </x-slot>

                <x-slot name="content">
                    <button wire:click="logout" class="w-full text-start">
                        <x-dropdown-link>Log Out</x-dropdown-link>
                    </button>
                </x-slot>
            </x-dropdown>
        </div>
    </div>

    <div x-show="open" class="fixed inset-0 z-50 lg:hidden" x-cloak>
        <div class="absolute inset-0 bg-slate-900/40" @click="open = false"></div>
        <aside class="pk-sidebar absolute inset-y-0 start-0 w-72 flex flex-col">
            <div class="p-4 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2" wire:navigate>
                        <x-application-logo class="h-9 w-9" />
                        <span class="font-semibold text-[#2f3349]">柔佛州羅氏公会 Portal</span>
                    </a>
                    <button @click="open = false" class="rounded-lg p-2 text-slate-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 6.75 10.5 10.5m0-10.5-10.5 10.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                {{-- Dashboard --}}
                <div class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="pk-sidebar-link {{ request()->routeIs('dashboard') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12a8.25 8.25 0 1 1 16.5 0v7.5a.75.75 0 0 1-.75.75h-15a.75.75 0 0 1-.75-.75V12Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20.25v-5.25a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v5.25" />
                        </svg>
                        Dashboard
                    </a>
                </div>

                {{-- User Management --}}
                @can('members.view')
                <div class="mt-5 space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">User Management</p>
                    <a href="{{ route('admin.members') }}" class="pk-sidebar-link {{ request()->routeIs('admin.members') ? 'pk-sidebar-link-active' : '' }}" wire:navigate><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.75a6 6 0 0 0-12 0" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" /></svg>Members</a>
                </div>
                @endcan

                {{-- QR Code --}}
                @can('member-qr.view')
                <div class="mt-5 space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">QR Code</p>
                    <a href="{{ route('admin.member-qr') }}" class="pk-sidebar-link {{ request()->routeIs('admin.member-qr') ? 'pk-sidebar-link-active' : '' }}" wire:navigate><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.008v.008H6.75V6.75Zm0 9.75h.008v.008H6.75v-.008Zm9.75-9.75h.008v.008h-.008V6.75Zm-3 6.75h3v3h-3v-3Z" /></svg>Member QR</a>
                </div>
                @endcan

                {{-- Engagement --}}
                @if(auth()->user()->can('announcements.view') || auth()->user()->can('notifications.view') || auth()->user()->can('platform-benefits.view') || auth()->user()->can('fundraising.view'))
                <div class="mt-5 space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Engagement</p>
                    @can('announcements.view')
                    <a href="{{ route('admin.announcements') }}" class="pk-sidebar-link {{ request()->routeIs('admin.announcements') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Announcements</a>
                    @endcan
                    @can('notifications.view')
                    <a href="{{ route('admin.notifications') }}" class="pk-sidebar-link {{ request()->routeIs('admin.notifications') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Notifications</a>
                    @endcan
                    @can('platform-benefits.view')
                    <a href="{{ route('admin.platform-benefits') }}" class="pk-sidebar-link {{ request()->routeIs('admin.platform-benefits') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Platform Benefits</a>
                    @endcan
                    @can('fundraising.view')
                    <a href="{{ route('admin.fundraising') }}" class="pk-sidebar-link {{ request()->routeIs('admin.fundraising') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Fundraising</a>
                    @endcan
                </div>
                @endif

                {{-- Settings --}}
                @if(auth()->user()->can('subscriptions.view') || auth()->user()->can('fraud_alerts.view') || auth()->user()->can('roles.view'))
                <div class="mt-5 space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Settings</p>
                    @can('subscriptions.view')
                    <a href="{{ route('admin.subscription-plans') }}" class="pk-sidebar-link {{ request()->routeIs('admin.subscription-plans') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Subscription Plans</a>
                    @endcan
                    @can('fraud_alerts.view')
                    <a href="{{ route('admin.fraud-alerts') }}" class="pk-sidebar-link {{ request()->routeIs('admin.fraud-alerts') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Fraud Alerts</a>
                    @endcan
                    @can('roles.view')
                    <a href="{{ route('admin.roles') }}" class="pk-sidebar-link {{ request()->routeIs('admin.roles') ? 'pk-sidebar-link-active' : '' }}" wire:navigate>Roles & Permissions</a>
                    @endcan
                </div>
                @endif
            </div>

            <div class="p-4 border-t border-slate-200">
                <button wire:click="logout" class="pk-sidebar-link w-full text-left">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6.75A2.25 2.25 0 0 0 4.5 5.25v13.5A2.25 2.25 0 0 0 6.75 21h6.75a2.25 2.25 0 0 0 2.25-2.25V15" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m18 15 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    Log Out
                </button>
            </div>
        </aside>
    </div>
</nav>
