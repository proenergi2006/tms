<?php

namespace App\Modules\Maintenance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Realisasi sparepart oleh SA (lihat WorkOrderController::realizeItems()) —
 * `items` WAJIB ada tapi boleh array kosong (menegaskan "tidak ada sparepart
 * terpakai"), berbeda dari StoreRequestRequest yang menjadikan `items`
 * opsional sepenuhnya untuk payload plan/estimasi awal.
 */
class RealizeWorkOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'present' (bukan 'required') — array kosong harus lolos validasi
            // untuk menegaskan "tidak ada sparepart terpakai" (lihat docblock
            // kelas ini); 'required' di Laravel menganggap [] sebagai kosong
            // dan akan menolaknya.
            // SA boleh sekaligus memperbarui diagnosa akhir saat realisasi
            // (temuan aktual setelah pekerjaan berjalan, bisa beda dari
            // diagnosa awal saat pengajuan) — opsional, tidak wajib diubah.
            'diagnosis' => ['nullable', 'string'],
            'items' => ['present', 'array'],
            'items.*.sparepart_id' => ['nullable', 'exists:spareparts,id'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.qty' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'min:0'],
            // Foto sparepart bekas (bukti fisik pemakaian) — cuma boleh
            // divalidasi sebagai file/image di sini; kewajibannya (hanya
            // ketika sparepart_id item itu terisi) dicek di withValidator()
            // karena 'required_if' bawaan Laravel membandingkan ke nilai
            // literal tertentu, bukan "field lain terisi apa saja".
            'items.*.photo' => ['nullable', 'file', 'image', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('items', []) as $index => $item) {
                if (! empty($item['sparepart_id']) && ! $this->hasFile("items.{$index}.photo")) {
                    $validator->errors()->add(
                        "items.{$index}.photo",
                        'Foto sparepart bekas wajib diunggah untuk item yang menggunakan sparepart.'
                    );
                }
            }
        });
    }
}
