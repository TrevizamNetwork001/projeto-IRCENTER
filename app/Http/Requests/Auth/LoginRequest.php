<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::attempt([
            'email' => Str::lower($this->string('email')->toString()),
            'password' => $this->string('password')->toString(),
            'active' => true,
        ], $this->boolean('remember'));

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey(), 60);
            RateLimiter::hit($this->accountThrottleKey(), 900);

            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas são inválidas ou o usuário está inativo.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountThrottleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        // Trava por email+IP (força bruta de um único ponto) e por email
        // isolado (força bruta distribuída por múltiplos IPs contra a
        // mesma conta), com limites e janelas independentes.
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
        return Str::transliterate(
            Str::lower($this->string('email')->toString()).'|'.$this->ip()
        );
    }

    private function accountThrottleKey(): string
    {
        return 'account|'.Str::transliterate(Str::lower($this->string('email')->toString()));
    }
}
