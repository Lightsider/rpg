<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitActionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actions' => 'required|array|max:4',
            'actions.*.type' => 'required|string|in:attack,attack_offhand,block,move',
            'actions.*.zone' => 'required_if:actions.*.type,attack,attack_offhand,block|string|in:head,torso,left_arm,right_arm,legs,body',
            'actions.*.target' => 'required_if:actions.*.type,move|array',
            'actions.*.target.x' => 'required_if:actions.*.type,move|integer',
            'actions.*.target.y' => 'required_if:actions.*.type,move|integer',
            'actions.*.blocks' => 'sometimes|array',
            'actions.*.blocks.*' => 'string|in:head,torso,left_arm,right_arm,legs',
        ];
    }
}
