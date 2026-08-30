<?php

namespace App\Http\Requests\Portal;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortalLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::guard('client')->attempt([
            'email' => Str::lower($this->string('email')->toString()),
            'password' => $this->string('password')->toString(),
            'active' => true,
        ]);

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey(), 60);
            RateLimiter::hit($this->accountThrottleKey(), 900);

            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas são inválidas ou o acesso está inativo.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountThrottleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->accountThrottleKey(), 20)) {
            event(new Lockout($this));

            $seconds = RateLimiter::availableIn($this->accountThrottleKey());

            throw ValidationException::withMessages([
                'email' => "Muitas tentativas para esta conta. Tente novamente em {$seconds} segundos.",
            ]);
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas de acesso. Tente novamente em {$seconds} segundos.",
        ]);
    }

    private function throttleKey(): string
    {
        return 'portal|'.Str::transliterate(
            Str::lower($this->string('email')->toString()).'|'.$this->ip()
        );
    }

    private function accountThrottleKey(): string
    {
        return 'portal-account|'.Str::transliterate(Str::lower($this->string('email')->toString()));
    }
}
