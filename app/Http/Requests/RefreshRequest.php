<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RefreshRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (app()->environment(['local', 'development', 'testing'])) {
            return true;
        }

        $requestedIp = $this->input('ip');

        // Empty body → refresh self (allowed)
        if ($requestedIp === null || $requestedIp === '') {
            return true;
        }

        // Production: body ip must match caller's real IP (from nginx X-Real-IP)
        return (string) $requestedIp === $this->callerIp();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ip' => ['nullable', 'string', 'max:64'],
        ];
    }

    private function callerIp(): string
    {
        if (app()->environment(['local', 'development', 'testing'])) {
            $override = $this->query('ip');
            if (is_string($override) && $override !== '') {
                return $override;
            }
        }

        return (string) $this->ip();
    }
}
