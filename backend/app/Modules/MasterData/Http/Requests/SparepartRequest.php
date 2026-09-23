<?php

namespace App\Modules\MasterData\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SparepartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sparepartId = $this->route('sparepart')?->id;

        return [
            // SKU dibuat otomatis oleh SparepartController::store() saat
            // pembuatan baru — hanya divalidasi bila memang dikirim (mis.
            // saat update, form mengirim balik nilai SKU yang sudah ada).
            'sku' => ['sometimes', 'string', 'max:50', Rule::unique('spareparts', 'sku')->ignore($sparepartId)],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'criteria' => ['nullable', Rule::in(['very_fast', 'fast', 'medium', 'slow', 'very_slow', 'non'])],
            'lead_time_months' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'unit_cost' => ['sometimes', 'numeric', 'min:0'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location' => ['nullable', 'string', 'max:100'],
            'stock_qty' => ['sometimes', 'integer', 'min:0'],
            'min_stock' => ['sometimes', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(['aman', 'order', 'dead_stock', 'non_aktif'])],
        ];
    }
}
