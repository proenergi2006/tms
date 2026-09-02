<?php

namespace App\Modules\Maintenance\Http\Resources;

use App\Modules\Maintenance\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_path' => $this->file_path,
            'caption' => $this->caption,
            'url' => app(AttachmentService::class)->url($this->resource),
            'uploaded_by' => $this->uploaded_by,
            'uploaded_at' => $this->uploaded_at,
        ];
    }
}
