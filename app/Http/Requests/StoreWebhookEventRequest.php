<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookEventRequest extends FormRequest
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
     */
    protected function prepareForValidation(): void
    {
        if ($this->hasHeader('Idempotency-Key') && ! $this->filled('idempotency_key')) {
            $this->merge([
                'idempotency_key' => $this->header('Idempotency-Key'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|array<string, mixed>>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'regex:/^[a-z0-9]+(?:\.[a-z0-9]+)*$/'],
            'data' => ['required', 'array', 'filled'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
