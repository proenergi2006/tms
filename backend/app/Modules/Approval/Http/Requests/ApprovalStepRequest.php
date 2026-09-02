<?php

namespace App\Modules\Approval\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dipakai untuk store() maupun update() pada ApprovalStepController — sama
 * seperti pola BranchRequest, tidak ada aturan yang berbeda antara buat baru
 * dan edit untuk resource ini. role_name divalidasi harus role yang benar-
 * benar ada (Rule::exists) supaya tidak ada tahap approval yang menunjuk
 * role fiktif.
 */
class ApprovalStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sequence_order' => ['required', 'integer', 'min:1'],
            'role_name' => ['required', 'string', Rule::exists('roles', 'name')],
            'label' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
