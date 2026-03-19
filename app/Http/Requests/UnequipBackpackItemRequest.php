<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Equipment\EquipmentSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnequipBackpackItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slot' => [
                'required',
                'string',
                Rule::in([EquipmentSlot::MAIN_HAND->value]),
            ],
        ];
    }
}
