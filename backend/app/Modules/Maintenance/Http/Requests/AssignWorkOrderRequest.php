<?php

namespace App\Modules\Maintenance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Menetapkan pelaksana (internal/eksternal) pada Work Order yang sudah
 * otomatis dibuat bersamaan dengan pengajuan — lihat catatan desain pada
 * WorkOrderController::store().
 */
class AssignWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'exists:requests,id'],
            'execution_type' => ['required', Rule::in(['internal', 'eksternal'])],
            'mechanic_id' => ['required_if:execution_type,internal', 'nullable', 'exists:mechanics,id'],
            'vendor_id' => ['required_if:execution_type,eksternal', 'nullable', 'exists:vendors,id'],
        ];
    }
}
