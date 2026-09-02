<?php

namespace App\Modules\MasterData\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $driverId = $this->route('driver')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            // Nullable: driver hasil sinkronisasi SYOP belum tentu punya
            // No. SIM tercatat di sana — diisi manual belakangan bila perlu.
            'license_number' => ['nullable', 'string', 'max:30', Rule::unique('drivers', 'license_number')->ignore($driverId)],
            'license_expiry' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'branch_id' => ['required', 'exists:branches,id'],
            'fleet_id' => ['nullable', 'exists:fleets,id'],
            'status' => ['sometimes', Rule::in(['aktif', 'nonaktif'])],
        ];
    }
}
