<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FleetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fleetId = $this->route('fleet')?->id;

        return [
            'plate_number' => ['required', 'string', 'max:20', Rule::unique('fleets', 'plate_number')->ignore($fleetId)],
            'fleet_type' => ['required', 'string', 'max:50'],
            'brand' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'chassis_number' => ['nullable', 'string', 'max:100'],
            'engine_number' => ['nullable', 'string', 'max:100'],
            'keur_number' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'ownership' => ['sometimes', Rule::in(['milik_sendiri', 'sewa', 'leasing'])],
            'leasing_status' => ['nullable', 'string', 'max:100'],
            'b3_dishub_number' => ['nullable', 'string', 'max:100'],
            'mutation_status' => ['sometimes', Rule::in(['tidak_ada', 'pindah', 'jual', 'ganti_nopol'])],
            'branch_id' => ['required', 'exists:branches,id'],
            'status' => ['sometimes', Rule::in(['aktif', 'maintenance', 'nonaktif'])],
            'service_interval_km' => ['nullable', 'integer', 'min:1'],
            'service_interval_engine_hours' => ['nullable', 'integer', 'min:1'],
            'service_interval_months' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
