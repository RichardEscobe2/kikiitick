<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de PATCH /api/user/perfil. Los patrones deben coincidir
 * EXACTAMENTE con el saneo en vivo (@keydown/@input) de Perfil.vue — el
 * frontend es solo cosmético/inmediato, esta clase es la autoridad final.
 */
class UpdatePerfilRequest extends FormRequest
{
    /**
     * La autorización real ya la resuelve el middleware auth:sanctum de la
     * ruta — cualquier usuario autenticado puede editar SU PROPIO perfil
     * (no se recibe ni se confía en ningún id externo en el payload).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 🛡️ Campos opcionales enviados como '' por el frontend cuando quedan
     * vacíos: sin esto, 'nullable' no los trataría como ausentes y el regex
     * de abajo los rechazaría igual que un valor inválido de verdad (mismo
     * patrón que AuthController::solicitudOrganizador()).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'telefono' => $this->filled('telefono') ? $this->input('telefono') : null,
            'rfc'      => $this->filled('rfc') ? $this->input('rfc') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre'   => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s\x{00C1}\x{00C9}\x{00CD}\x{00D3}\x{00DA}\x{00E1}\x{00E9}\x{00ED}\x{00F3}\x{00FA}\x{00D1}\x{00F1}]+$/u'],
            'telefono' => ['nullable', 'string', 'digits:10'],
            'rfc'      => ['nullable', 'string', 'regex:/^[A-Z0-9]{12,13}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.regex'   => 'El nombre solo puede contener letras y espacios (sin números ni símbolos).',
            'telefono.digits' => 'El teléfono debe tener exactamente 10 dígitos.',
            'rfc.regex'      => 'El RFC debe tener entre 12 y 13 caracteres (solo letras mayúsculas y números).',
        ];
    }
}
