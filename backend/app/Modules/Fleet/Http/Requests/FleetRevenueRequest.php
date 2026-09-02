<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FleetRevenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'date_format:Y-m'],
            'source_po_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('fleet_revenues')
                    ->where('fleet_id', $this->route('fleet')->id)
                    ->where('period', $this->input('period')),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_po_number.unique' => 'Pendapatan untuk cabang & periode ini dengan No. PO tersebut sudah tercatat.',
        ];
    }
}
