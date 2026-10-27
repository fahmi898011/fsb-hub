@php($selected = old('permissions', isset($role) ? $role->permissions->pluck('name')->all() : []))

<div class="space-y-5">
    @foreach ($groups as $label => $permissions)
        <div class="card">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-ink-900">{{ $label }}</h3>
                <span class="text-xs text-ink-500">{{ $permissions->count() }} permission</span>
            </div>

            <div class="grid grid-cols-1 gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($permissions as $permission)
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-ink-200 p-3 transition-colors hover:bg-ink-50">
                        <input type="checkbox"
                               name="permissions[]"
                               value="{{ $permission->name }}"
                               @checked(in_array($permission->name, $selected, true))
                               class="mt-0.5 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-ink-800">{{ $permission->name }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
