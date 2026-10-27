@php($isEdit = $user->exists)

<div class="space-y-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="form-label">Nama Lengkap <span class="text-red-500">*</span></label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required class="form-input" placeholder="mis. Budi Santoso">
            @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="form-label">Email <span class="text-red-500">*</span></label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required class="form-input" placeholder="nama@fsb.co.id">
            @error('email')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="employee_id" class="form-label">NIP / ID Karyawan</label>
            <input id="employee_id" name="employee_id" type="text" value="{{ old('employee_id', $user->employee_id ?? '') }}" class="form-input" placeholder="mis. 2024001">
            @error('employee_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="department_id" class="form-label">Divisi</label>
            <select id="department_id" name="department_id" class="form-input">
                <option value="">— Belum ditentukan —</option>
                @foreach ($departments ?? [] as $department)
                    <option value="{{ $department->id }}" @selected((string) old('department_id', $user->department_id ?? '') === (string) $department->id)>
                        {{ $department->name }}
                    </option>
                @endforeach
            </select>
            @error('department_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            {{-- Kolom teks lama. Dibiarkan agar data impor lama tetap bisa
                 disunting; gunakan pilihan Divisi di atas untuk data baru. --}}
            <label for="department" class="form-label">Departemen (teks bebas)</label>
            <input id="department" name="department" type="text" value="{{ old('department', $user->department ?? '') }}" class="form-input" placeholder="mis. Quality Assurance">
            <p class="mt-1 text-xs text-ink-400">Pilih Divisi di atas bila memungkinkan, agar rekap per divisi akurat.</p>
            @error('department')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="phone" class="form-label">Telepon</label>
            <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}" class="form-input" placeholder="mis. 0812xxxxxxx">
            @error('phone')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="role" class="form-label">Role <span class="text-red-500">*</span></label>
            <select id="role" name="role" required class="form-input">
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected(old('role', $user->roles->first()?->name ?? 'employee') === $role->name)>
                        {{ \App\Models\User::roleLabelFor($role->name) }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-ink-400">Admin mengatur wadah aplikasi; QA mengelola isi informasi.</p>
            @error('role')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="status" class="form-label">Status <span class="text-red-500">*</span></label>
            <select id="status" name="status" required class="form-input">
                <option value="active" @selected(old('status', $user->status ?? 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $user->status ?? '') === 'inactive')>Nonaktif</option>
            </select>
            @error('status')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 border-t border-ink-100 pt-5 sm:grid-cols-2">
        <div>
            <label for="password" class="form-label">
                Password @if (! $isEdit)<span class="text-red-500">*</span>@endif
            </label>
            <input id="password" name="password" type="password" autocomplete="new-password" class="form-input"
                   placeholder="{{ $isEdit ? 'Kosongkan bila tidak diubah' : 'Minimal 8 karakter' }}">
            @if ($isEdit)
                <p class="mt-1 text-xs text-ink-400">Biarkan kosong bila tidak ingin mengubah password.</p>
            @endif
            @error('password')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="form-input">
        </div>
    </div>
</div>
