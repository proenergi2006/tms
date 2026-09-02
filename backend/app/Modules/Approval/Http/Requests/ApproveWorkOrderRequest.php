<?php

namespace App\Modules\Approval\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
