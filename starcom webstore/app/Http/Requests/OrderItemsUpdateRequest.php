<?php

namespace App\Http\Requests;

use App\Rules\ValidJsonOrder;
use Illuminate\Foundation\Http\FormRequest;

class OrderItemsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'numeric'],
            'subtotal'    => ['required', 'numeric'],
            'discount'    => ['nullable', 'numeric'],
            'tax'         => ['required', 'numeric'],
            'total'       => ['required', 'numeric'],
            'products'    => ['required', 'json', new ValidJsonOrder],
        ];
    }
}
