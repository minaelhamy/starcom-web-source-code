<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnAndRefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'return_reason_id' => ['required', 'numeric'],
            'note'             => ['nullable', 'string', 'max:5000'],
            'order_id'         => ['required', 'numeric'],
            'order_serial_no'  => ['required', 'string'],
            'products'         => ['required', 'json'],
            'image[]'          => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(){
        return [
            "return_reason_id.required" => "The return reason field is required."
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $products = json_decode($this->products, true);

            if (!is_array($products) || count($products) === 0) {
                $validator->errors()->add('products', trans('all.message.product_invalid'));
                return;
            }

            foreach ($products as $product) {
                $quantity = round((float) ($product['quantity'] ?? 0), 2);
                $maxQuantity = round((float) ($product['order_quantity'] ?? 0), 2);

                if ($quantity <= 0) {
                    $validator->errors()->add('products', trans('all.message.product_quantity_invalid'));
                    return;
                }

                if ($maxQuantity > 0 && $quantity - $maxQuantity > 0.00001) {
                    $validator->errors()->add('products', trans('all.message.product_quantity_invalid'));
                    return;
                }
            }
        });
    }
}
