<?php

namespace App\Modules\Approval\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
