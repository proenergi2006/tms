<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FleetLegalDocRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', Rule::in(['STNK', 'KIR', 'PAJAK', 'ASURANSI', 'IZIN'])],
            'doc_number' => ['nullable', 'string', 'max:50'],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['required', 'date'],
            'file_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
