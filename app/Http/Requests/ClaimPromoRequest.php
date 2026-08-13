<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimPromoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => strtoupper(trim($code))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'between:6,12', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The promo code may only contain Latin letters and numbers.',
            'code.between' => 'The promo code must be between 6 and 12 characters.',
        ];
    }
}
