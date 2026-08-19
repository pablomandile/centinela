<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarProyectoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $proyecto = $this->route('proyecto');

        return [
            'nombre' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:60',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('proyectos', 'slug')->ignore($proyecto),
            ],
            // `url` con esquema obligatorio: sin él, `parse_url` no encuentra el host
            // y el chequeo del certificado no sabe a qué conectarse.
            'url' => ['required', 'url:http,https', 'max:255'],
            'repo_url' => ['nullable', 'url:https', 'max:255'],
            'usa_inertia' => ['boolean'],
            'es_pwa' => ['boolean'],
            'tiene_bundle' => ['boolean'],
            'activo' => ['boolean'],
            'palabra_clave' => ['nullable', 'string', 'max:120'],
            // Menos de 5 minutos no tiene sentido: el scheduler corre cada 5.
            'intervalo_minutos' => ['required', 'integer', 'min:5', 'max:1440'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'usa_inertia' => 'usa Inertia',
            'es_pwa' => 'es PWA',
            'tiene_bundle' => 'tiene bundle',
            'intervalo_minutos' => 'intervalo',
            'repo_url' => 'repositorio',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El identificador solo puede tener minúsculas, números y guiones.',
            'intervalo_minutos.min' => 'El intervalo no puede ser menor a 5 minutos: el scheduler corre cada 5.',
        ];
    }
}
