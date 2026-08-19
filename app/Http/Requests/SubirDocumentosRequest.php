<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubirDocumentosRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Varios de una: la documentación de un proyecto son cinco o seis
            // archivos, y subirlos de a uno con el celular en la mano es lo que
            // garantiza que no se suban nunca.
            'archivos' => ['required', 'array', 'min:1', 'max:20'],

            /*
             * `extensions` y no `mimes`: un `.md` llega con content-type
             * `application/octet-stream` o `text/plain` según el sistema del que
             * salga, así que validar por MIME rechaza archivos perfectamente
             * válidos. La regla `extensions` mira la extensión real del archivo.
             *
             * 10 MB por archivo: los `.md` pesan kilobytes y un PDF de texto pocos
             * megas. El tope existe por la cuota de disco del hosting compartido.
             */
            'archivos.*' => ['file', 'max:10240', 'extensions:md,markdown,pdf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivos.required' => 'Elegí al menos un archivo.',
            'archivos.*.extensions' => 'Solo se pueden subir archivos .md o .pdf.',
            'archivos.*.max' => 'Cada archivo tiene que pesar menos de 10 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['archivos' => 'archivos', 'archivos.*' => 'archivo'];
    }
}
