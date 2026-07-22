<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * Sanitizes string inputs before validation rules are applied.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'name' => $this->has('name') ? strip_tags((string) $this->input('name')) : null,
            'email' => $this->has('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'cpf' => $this->has('cpf') ? preg_replace('/\D/', '', (string) $this->input('cpf')) : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'cpf' => ['required', 'string', 'size:11', 'regex:/^\d{11}$/', 'unique:users,cpf'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'O e-mail já está em uso.',
            'cpf.unique' => 'O CPF já está em uso.',
            'cpf.size' => 'O CPF deve conter exatamente 11 dígitos.',
            'cpf.regex' => 'O CPF deve conter apenas dígitos numéricos.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ];
    }
}
