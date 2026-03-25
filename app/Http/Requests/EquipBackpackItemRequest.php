<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Equipment\EquipmentSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipBackpackItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('type', 'weapon'),
            ],
            'slot' => [
                'required',
                'string',
                Rule::in([EquipmentSlot::MAIN_HAND->value]),
            ],
        ];
    }
}
