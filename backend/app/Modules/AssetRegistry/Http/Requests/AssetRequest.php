<?php

namespace App\Modules\AssetRegistry\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $assetId = $this->route('asset')?->id;

        return [
            'asset_code' => ['required', 'string', 'max:30', Rule::unique('asset_registry', 'asset_code')->ignore($assetId)],
            'category' => ['required', Rule::in(['IT', 'GA'])],
            'name' => ['required', 'string', 'max:150'],
            'pic' => ['nullable', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'purchase_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['aktif', 'rusak', 'dihapuskan'])],
        ];
    }
}
