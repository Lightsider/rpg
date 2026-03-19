<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterLoadoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strength' => 'required|integer|min:0|max:100',
            'dexterity' => 'required|integer|min:0|max:100',
            'constitution' => 'required|integer|min:0|max:100',
            'wit' => 'required|integer|min:0|max:100',
        ];
    }
}
