<?php

namespace App\Modules\Maintenance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPerbaikan = fn () => $this->input('type') === 'perbaikan';

        return [
            'type' => ['required', Rule::in(['perbaikan', 'sparepart', 'restock', 'pembelian', 'lainnya'])],
            'maintenance_nature' => [Rule::requiredIf($isPerbaikan), 'nullable', Rule::in(['preventive', 'corrective'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'fleet_id' => ['nullable', 'exists:fleets,id'],
            'diagnosis' => [Rule::requiredIf($isPerbaikan), 'nullable', 'string'],
            'description' => ['required', 'string'],
            // No. TAR DIBUAT SISTEM (lihat Request::generateTarNo(),
            // RequestController::store()) — bukan input klien, sengaja tidak
            // divalidasi/diterima di sini.
            'estimated_days' => [Rule::requiredIf($isPerbaikan), 'nullable', 'integer', 'min:1'],
            // Laporan temuan lapangan — dilaporkan manual SA saat pengecekan
            // fisik, terpisah dari Fleet::currentOdometer() (lihat migrasi).
            'odometer_km' => [Rule::requiredIf($isPerbaikan), 'nullable', 'integer', 'min:0'],
            'trouble_date' => [Rule::requiredIf($isPerbaikan), 'nullable', 'date'],
            'suggestion' => [Rule::requiredIf($isPerbaikan), 'nullable', 'string'],
            'action_taken' => [Rule::requiredIf($isPerbaikan), 'nullable', 'string'],
            'execution_type' => [Rule::requiredIf($isPerbaikan), 'nullable', Rule::in(['internal', 'eksternal'])],
            'mechanic_id' => ['nullable', 'required_if:execution_type,internal', 'exists:mechanics,id'],
            'vendor_id' => ['nullable', 'required_if:execution_type,eksternal', 'exists:vendors,id'],
            'items' => ['nullable', 'array'],
            'items.*.sparepart_id' => ['nullable', 'exists:spareparts,id'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.qty' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
