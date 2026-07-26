<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerServiceLeadProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:65535'],
            'city' => ['nullable', 'string', 'max:190'],
            'area' => ['nullable', 'string', 'max:190'],
            'distribution_route' => ['nullable', 'string', 'max:190'],
            'latitude' => ['nullable', 'string', 'max:100'],
            'longitude' => ['nullable', 'string', 'max:100'],
            'estimated_average_monthly_purchase' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
