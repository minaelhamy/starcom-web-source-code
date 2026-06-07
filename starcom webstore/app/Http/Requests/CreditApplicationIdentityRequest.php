<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditApplicationIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'national_id_number' => ['required', 'string', 'max:32'],
        ];
    }
}
