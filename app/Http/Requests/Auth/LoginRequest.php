<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Percobaan masuk yang gagal WAJIB terekam (PRD §16): tanpa ini
            // tidak ada bukti percobaan akses yang ditolak.
            // AGENT: asisten | 2026-10-10 | catat login gagal | modul Audit Log, §11.15
            $this->logFailure('auth.login.failed', 'warning');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        // Diblokir karena batas percobaan: ini sinyal keamanan, dicatat
        // terpisah dari kegagalan kredensial biasa.
        $this->logFailure('auth.lockout', 'critical', 'denied');

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Catat percobaan masuk yang ditolak.
     *
     * Tidak ada pelaku yang sah di titik ini, jadi `user_id` dibiarkan kosong:
     * identitasnya disimpan sebagai potret (`actor_email`) dan, bila akunnya
     * memang ada, sebagai entitas yang disasar. Nilai apa pun tidak pernah
     * memuat kata sandi.
     *
     * AGENT: asisten | 2026-10-10 | catat login gagal | modul Audit Log, §11.15
     */
    private function logFailure(string $action, string $severity, string $result = 'failed'): void
    {
        try {
            $email = (string) $this->string('email');
            $target = $email !== '' ? User::query()->where('email', $email)->first() : null;

            app(AuditLogService::class)->log($action, $target, [], [], [
                'module' => 'auth',
                'category' => 'auth',
                'severity' => $severity,
                'result' => $result,
                'actor_email' => $email !== '' ? $email : null,
                'subject_label' => $email !== '' ? $email : null,
                'description' => ($action === 'auth.lockout'
                    ? 'Percobaan masuk diblokir sementara (batas percobaan)'
                    : 'Percobaan masuk gagal')
                    .($email !== '' ? ': '.$email : ''),
            ]);
        } catch (Throwable) {
            // Jejak audit tidak boleh menggagalkan proses masuk.
        }
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
