<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component {
    public string $name = '';
    public string $accountEmail = '';
    public ?int $accountUserId = null;
    public string $successMessage = '';
    public string $toastType = 'success';
    public array $selectedPermissions = [];
    public ?int $editingRoleId = null;
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public ?string $lastCreatedPassword = null;

    public function mount(): void
    {
        $this->selectedPermissions = $this->allPermissionNames();
    }

    /** @return list<string> */
    private function allPermissionNames(): array
    {
        return Permission::query()->orderBy('name')->pluck('name')->all();
    }

    public function getRolesProperty()
    {
        return Role::query()
            ->with('permissions')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->get();
    }

    public function getPermissionsProperty()
    {
        $permissions = Permission::query()->orderBy('name')->pluck('name')->all();
        
        // Group permissions by resource
        $grouped = [];
        foreach ($permissions as $permission) {
            if (!str_contains($permission, '.')) {
                // Legacy permission without resource prefix
                if (!isset($grouped['other'])) {
                    $grouped['other'] = [];
                }
                $grouped['other'][] = $permission;
                continue;
            }
            
            [$resource, $action] = explode('.', $permission, 2);
            if (!isset($grouped[$resource])) {
                $grouped[$resource] = [];
            }
            $grouped[$resource][] = $permission;
        }
        
        return $grouped;
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function sortIcon(string $field): string
    {
        if ($this->sortField !== $field) return '';
        return $this->sortDirection === 'asc' ? '↑' : '↓';
    }

    public function saveRole(): void
    {
        abort_unless(auth()->user()->can('roles.create') || auth()->user()->can('roles.edit'), 403);
        $isEditing = (bool) $this->editingRoleId;
        $guardName = config('auth.defaults.guard', 'web');
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', $guardName))
                    ->ignore($this->editingRoleId),
            ],
            'selectedPermissions' => ['array'],
        ];

        if (! $this->editingRoleId) {
            $rules['accountEmail'] = ['required', 'string', 'email', 'max:255', 'unique:users,email'];
        } else {
            $rules['accountEmail'] = ['nullable', 'string', 'email', 'max:255', 'unique:users,email,'.($this->accountUserId ?? 'NULL')];
        }

        $this->validate($rules);

        if ($isEditing) {
            $role = Role::findOrFail($this->editingRoleId);
            $role->update(['name' => $this->name]);
        } else {
            $role = Role::create([
                'name' => $this->name,
                'guard_name' => $guardName,
            ]);
        }
        $role->syncPermissions($this->selectedPermissions);

        // On create, also provision a login account and assign this role.
        if (! $this->editingRoleId) {
            $generatedPassword = $this->generatePasswordFromEmail($this->accountEmail);

            $user = User::create([
                'name' => $this->name,
                'email' => $this->accountEmail,
                'password' => $generatedPassword,
            ]);

            $user->assignRole($role);
            $this->lastCreatedPassword = $generatedPassword;
        } else {
            $this->lastCreatedPassword = null;
            if ($this->accountEmail !== '') {
                if ($this->accountUserId) {
                    $user = User::find($this->accountUserId);
                    if ($user) {
                        $emailChanged = strcasecmp((string) $user->email, (string) $this->accountEmail) !== 0;
                        $updateData = [
                            'name' => $this->name,
                            'email' => $this->accountEmail,
                        ];

                        if ($emailChanged) {
                            $generatedPassword = $this->generatePasswordFromEmail($this->accountEmail);
                            $updateData['password'] = $generatedPassword;
                            $this->lastCreatedPassword = $generatedPassword;
                        }

                        $user->update($updateData);
                    }
                } else {
                    // Legacy role without login account: create one when email is provided.
                    $generatedPassword = $this->generatePasswordFromEmail($this->accountEmail);
                    $user = User::create([
                        'name' => $this->name,
                        'email' => $this->accountEmail,
                        'password' => $generatedPassword,
                    ]);
                    $user->assignRole($role);
                    $this->accountUserId = $user->id;
                    $this->lastCreatedPassword = $generatedPassword;
                }
            }
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $this->editingRoleId ? 'role_updated' : 'role_created',
            'entity_type' => Role::class,
            'entity_id' => $role->id,
            'after_data' => [
                'name' => $role->name,
                'permissions' => $this->selectedPermissions,
                'account_email' => $this->accountEmail ?: null,
            ],
        ]);

        $this->showToast(
            $isEditing
                ? 'Role updated successfully.'
                : 'Role and login account created successfully.'
        );

        if ($isEditing) {
            // Keep the same role loaded after update, so admin can continue editing without clicking Edit again.
            $this->editingRoleId = $role->id;
            $this->name = $role->name;
            $this->selectedPermissions = $role->permissions()->pluck('name')->all();
        } else {
            $this->reset(['name', 'accountEmail', 'accountUserId', 'selectedPermissions', 'editingRoleId']);
            $this->selectedPermissions = $this->allPermissionNames();
        }
    }

    public function editRole(int $id): void
    {
        abort_unless(auth()->user()->can('roles.edit'), 403);
        $role = Role::findOrFail($id);
        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
        $linkedUser = User::role($role->name)->orderBy('id')->first();
        $this->accountUserId = $linkedUser?->id;
        $this->accountEmail = $linkedUser?->email ?? '';
        $this->lastCreatedPassword = null;
        $this->showToast('Role details loaded successfully.');
        $this->js("
            window.dispatchEvent(new CustomEvent('rbac-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function viewRole(int $id): void
    {
        abort_unless(auth()->user()->can('roles.view'), 403);
        $role = Role::findOrFail($id);
        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
        $linkedUser = User::role($role->name)->orderBy('id')->first();
        $this->accountUserId = $linkedUser?->id;
        $this->accountEmail = $linkedUser?->email ?? '';
        $this->lastCreatedPassword = null;
        $this->showToast('Role details loaded successfully.');
        $this->js("
            window.dispatchEvent(new CustomEvent('rbac-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function deleteRole(int $id): void
    {
        abort_unless(auth()->user()->can('roles.delete'), 403);
        $role = Role::findOrFail($id);
        $name = $role->name;
        $role->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'role_deleted',
            'entity_type' => Role::class,
            'entity_id' => $id,
            'before_data' => ['name' => $name],
        ]);
        $this->showToast('Role deleted.');
    }

    public function startCreate(): void
    {
        $this->lastCreatedPassword = null;
        $this->reset(['name', 'accountEmail', 'accountUserId', 'selectedPermissions', 'editingRoleId']);
        $this->selectedPermissions = $this->allPermissionNames();
        $this->showToast('Ready to create a new role.');
        $this->js("
            window.dispatchEvent(new CustomEvent('rbac-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    private function showToast(string $message, string $type = 'success'): void
    {
        $this->successMessage = __($message);
        $this->toastType = $type === 'error' ? 'error' : 'success';
    }

    private function generatePasswordFromEmail(string $email): string
    {
        $prefix = Str::before($email, '@');
        $cleanPrefix = preg_replace('/[^a-zA-Z0-9]/', '', $prefix) ?: 'user';
        $seed = strtoupper(substr(md5($email), 0, 4));

        return $cleanPrefix.'@'.$seed;
    }
}; ?>

<x-slot name="header">
    <div>
        <h1 class="pk-page-title">{{ __('Role-Based Access Control') }}</h1>
        <p class="pk-subtitle">{{ __('Super Admin can create, edit and delete roles with configurable permissions.') }}</p>
    </div>
</x-slot>

<div class="space-y-6">
    <div
        x-data="{ message: $wire.entangle('successMessage'), type: $wire.entangle('toastType') }"
        x-cloak
        class="pointer-events-none z-[150]"
        style="position: fixed; right: 0.75rem; bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px)); width: calc(100vw - 1.5rem); max-width: 24rem;"
    >
        <div
            x-show="message"
            x-transition.opacity.duration.200ms
            class="pointer-events-auto rounded-xl border p-4 text-sm shadow-lg"
            :style="type === 'error'
                ? 'border-color:#fecdd3;background-color:#fff1f2;color:#9f1239;'
                : 'border-color:#bbf7d0;background-color:#f0fdf4;color:#166534;'"
            x-init="$watch('message', value => { if (value) { setTimeout(() => message = '', 2800) } })"
        >
            <div class="flex items-center justify-between gap-3">
                <p x-text="message"></p>
                <button
                    type="button"
                    class="text-xs font-semibold"
                    :style="type === 'error' ? 'color:#be123c;' : 'color:#15803d;'"
                    @click="message = ''"
                >
                    Dismiss
                </button>
            </div>
        </div>
    </div>

    {{-- Search bar --}}
    <section class="pk-filter-bar">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by role name...') }}" class="pk-input w-full sm:max-w-sm">
    </section>

<div class="grid gap-6 xl:grid-cols-3">
    {{-- Form card (collapsible on mobile) --}}
    <section
        class="pk-card w-full self-start p-5 xl:col-span-1"
        x-data="{ open: window.matchMedia('(min-width: 768px)').matches }"
        x-init="
            const mq = window.matchMedia('(min-width: 768px)');
            const sync = () => { if (mq.matches) open = true; };
            if (mq.addEventListener) mq.addEventListener('change', sync);
            else mq.addListener(sync);
        "
        @rbac-form-open.window="open = true"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-800">{{ __($editingRoleId ? 'Edit Role' : 'Create Role') }}</h2>
            <div class="flex items-center gap-2">
                @if($editingRoleId)
                    @can('roles.create')
                    <button type="button" class="pk-btn-primary shrink-0" wire:click="startCreate">
                        Create New
                    </button>
                    @endcan
                @endif
                <button
                    type="button"
                    class="flex items-center justify-center rounded-xl border p-2 text-slate-500 transition hover:bg-slate-50 md:hidden"
                    @click="open = !open"
                >
                    <svg x-show="!open" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    <svg x-show="open" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                </button>
            </div>
        </div>

        <div x-show="open" x-collapse>
            @if($errors->any())
                <div class="mt-4 rounded-xl border px-3 py-2 text-sm font-medium" style="border-color:#fda4af;background-color:#fff1f2;color:#9f1239;">
                    Please fill in all required fields marked with *.
                </div>
            @endif

            <form wire:submit="saveRole" class="mt-4 space-y-4">
                <x-admin.form.group label="Role Name *">
                    <input type="text" wire:model="name" class="{{ $errors->has('name') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" placeholder="{{ __('Example: Finance Admin') }}">
                    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>
                <x-admin.form.group label="Login Email {{ $editingRoleId ? '' : '*' }}">
                    <input type="email" wire:model="accountEmail" class="{{ $errors->has('accountEmail') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" placeholder="{{ __('role-login@company.com') }}">
                    @error('accountEmail')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-400">
                        @if($editingRoleId)
                            Update this email to change the role login account. If role has no account yet, entering email will create one.
                        @else
                            A login account will be created automatically for this role.
                        @endif
                    </p>
                </x-admin.form.group>
                <x-admin.form.group label="Permissions">
                    <div class="space-y-3">
                        @foreach($this->permissions as $resource => $resourcePermissions)
                            <div class="rounded-lg border border-slate-200 p-3">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">{{ ucwords(str_replace('-', ' ', $resource)) }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach($resourcePermissions as $permission)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission }}" class="rounded border-slate-300">
                                            <span class="text-slate-700">{{ ucfirst(str_contains($permission, '.') ? explode('.', $permission)[1] : $permission) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-admin.form.group>
                @if(auth()->user()->can('roles.create') || auth()->user()->can('roles.edit'))
                    <button class="pk-btn-primary w-full">{{ __($editingRoleId ? 'Update Role' : 'Create Role') }}</button>
                @endif
            </form>
            @if($lastCreatedPassword)
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ __('Temporary login password') }}</p>
                    <p class="mt-1 text-sm text-emerald-800">{{ __('Temporary password:') }} <span class="font-mono font-semibold">{{ $lastCreatedPassword }}</span></p>
                </div>
            @endif
        </div>
    </section>

    <section class="xl:col-span-2">
        {{-- Mobile card view --}}
        <div class="pk-mobile-only space-y-3">
            @foreach($this->roles as $role)
                <article class="pk-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-800">{{ $role->name }}</p>
                        @if($role->name === 'Super Admin')
                            <span class="pk-badge-danger">{{ __('Full system control') }}</span>
                        @else
                            <span class="pk-badge-success">{{ __('Scoped administration') }}</span>
                        @endif
                    </div>
                    @php
                        $mobileGrouped = [];
                        foreach ($role->permissions->pluck('name') as $perm) {
                            if (str_contains($perm, '.')) {
                                [$res, $act] = explode('.', $perm, 2);
                                $mobileGrouped[$res][] = $act;
                            }
                        }
                    @endphp
                    @if(empty($mobileGrouped))
                        <p class="mt-2 text-xs text-slate-400">{{ __('No permissions') }}</p>
                    @else
                        <div class="mt-2 space-y-1.5">
                            @foreach($mobileGrouped as $resource => $actions)
                                <div class="flex flex-wrap items-center gap-1">
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ ucwords(str_replace('-', ' ', $resource)) }}:</span>
                                    @foreach($actions as $action)
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ ucfirst($action) }}</span>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if(auth()->user()->can('roles.edit') || auth()->user()->can('roles.delete'))
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button class="pk-btn-secondary px-2 py-2 text-xs" wire:click="viewRole({{ $role->id }})">{{ __('View') }}</button>
                            @can('roles.edit')
                            <button class="pk-btn-secondary px-2 py-2 text-xs" wire:click="editRole({{ $role->id }})">{{ __('Edit') }}</button>
                            @endcan
                            @can('roles.delete')
                            <button class="pk-btn-secondary px-2 py-2 text-xs text-red-600" wire:click="deleteRole({{ $role->id }})">{{ __('Delete') }}</button>
                            @endcan
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        {{-- Desktop table view --}}
        <div class="pk-desktop-only">
            <x-admin.table.wrapper>
                <table class="pk-table w-full">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap w-40">
                                <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1">{{ __('Role') }} <span>{{ $this->sortIcon('name') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap w-36">{{ __('Access Level') }}</th>
                            <th>{{ __('Permissions') }}</th>
                            <th class="whitespace-nowrap w-32">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->roles as $role)
                            <tr class="align-top">
                                <td class="whitespace-nowrap font-semibold text-slate-700">{{ $role->name }}</td>
                                <td class="whitespace-nowrap">
                                    @if($role->name === 'Super Admin')
                                        <span class="pk-badge-danger">{{ __('Full system control') }}</span>
                                    @else
                                        <span class="pk-badge-success">{{ __('Scoped administration') }}</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    @php
                                        $grouped = [];
                                        foreach ($role->permissions->pluck('name') as $perm) {
                                            if (str_contains($perm, '.')) {
                                                [$res, $act] = explode('.', $perm, 2);
                                                $grouped[$res][] = $act;
                                            }
                                        }
                                    @endphp
                                    @if(empty($grouped))
                                        <span class="text-xs text-slate-400">{{ __('No permissions') }}</span>
                                    @else
                                        <div class="flex flex-wrap gap-y-2 gap-x-3">
                                            @foreach($grouped as $resource => $actions)
                                                <div class="flex items-center gap-1 flex-wrap">
                                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ ucwords(str_replace('-', ' ', $resource)) }}:</span>
                                                    @foreach($actions as $action)
                                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ ucfirst($action) }}</span>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <button class="pk-btn-secondary px-3 py-2 text-xs" wire:click="viewRole({{ $role->id }})">{{ __('View') }}</button>
                                    @can('roles.edit')
                                        <button class="pk-btn-secondary px-3 py-2 text-xs" wire:click="editRole({{ $role->id }})">{{ __('Edit') }}</button>
                                    @endcan
                                    @can('roles.delete')
                                        <button class="pk-btn-secondary px-3 py-2 text-xs text-red-600" wire:click="deleteRole({{ $role->id }})">{{ __('Delete') }}</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-admin.table.wrapper>
        </div>
    </section>
</div>
</div>
