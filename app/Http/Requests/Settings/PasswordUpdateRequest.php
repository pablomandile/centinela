<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * A quien entró con Google **no** se le pide la contraseña anterior: no
     * tiene ninguna, así que exigirla dejaría la puerta de emergencia cerrada con
     * llave del lado de adentro —nunca podría definir una para el día que Google
     * falle—.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = ['password' => $this->passwordRules()];

        if (filled($this->user()?->password)) {
            $reglas['current_password'] = $this->currentPasswordRules();
        }

        return $reglas;
    }
}
