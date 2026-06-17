<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidJsonOrder implements Rule
{
    public $message = '';
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $requestItems = json_decode($value);
        if (!is_array($requestItems) || count($requestItems) == 0) {
            $this->message = 'This :attribute must be json.';
            return false;
        }

        foreach ($requestItems as $index => $item) {
            $quantity = $item->quantity ?? null;

            if (!is_numeric($quantity)) {
                $this->message = 'الكمية في أحد المنتجات غير صحيحة.';
                return false;
            }

            $quantity = (float) $quantity;
            if ($quantity <= 0) {
                $this->message = 'يجب أن تكون الكمية أكبر من صفر.';
                return false;
            }

            if (round($quantity, 2) != $quantity) {
                $this->message = 'يمكن إدخال الكمية حتى منزلتين عشريتين فقط.';
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->message;
    }
}
