<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman Pengaturan Fitur (saklar on/off).
 *
 * AGENT: asisten | 2026-10-16 | buat berkas baru | modul Pengaturan Fitur
 *
 * Alasan halaman ini ada: beberapa perilaku modul Quiz/Ujian bergantung pada
 * kebijakan operasional yang bisa berubah kapan saja (mis. apakah agent boleh
 * melihat foto lembar jawabannya, apakah quiz pilihan ganda lama ditampilkan).
 * Tanpa saklar, setiap perubahan sekecil itu menuntut developer + deploy.
 *
 * Hanya Admin yang boleh masuk (gate `admin-access` di routes). Setiap
 * perubahan dicatat ke audit log beserta nilai sebelum/sesudah, karena
 * mematikan fitur mengubah perilaku seluruh pengguna.
 */
class FeatureSettingController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('admin.settings.features', [
            'settings' => Setting::query()
                ->orderBy('group')
                ->orderBy('label')
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->input('settings', []);

        if (! is_array($values)) {
            $values = [];
        }

        $before = Setting::query()->pluck('value', 'key')->all();
        $after = [];

        foreach (Setting::query()->get() as $setting) {
            // Checkbox yang tidak dicentang tidak dikirim browser. Karena itu
            // form menyertakan hidden input bernilai 0 sebelum checkbox; di sini
            // cukup membaca nilai apa pun yang datang.
            $enabled = filter_var($values[$setting->key] ?? false, FILTER_VALIDATE_BOOL);

            Setting::put($setting->key, $enabled);

            $after[$setting->key] = $enabled ? '1' : '0';
        }

        $changes = array_diff_assoc($after, $before);

        if ($changes !== []) {
            $this->audit->log('setting.features.update', null, [], $changes, [
                'module' => 'setting',
                'subject_label' => 'Pengaturan Fitur',
                'description' => 'Mengubah saklar fitur: '.implode(', ', array_keys($changes)),
            ]);
        }

        return redirect()
            ->route('admin.features.index')
            ->with('success', 'Pengaturan fitur berhasil disimpan.');
    }
}
