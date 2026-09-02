<?php

namespace App\Modules\Maintenance\Services;

use App\Models\User;
use App\Modules\Maintenance\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttachmentService
{
    public function store(Model $attachable, UploadedFile $file, User $uploader, ?string $caption = null): Attachment
    {
        $path = $file->store('attachments', 'public');

        return $attachable->attachments()->create([
            'file_path' => $path,
            'caption' => $caption,
            'uploaded_by' => $uploader->id,
            'uploaded_at' => now(),
        ]);
    }

    public function url(Attachment $attachment): string
    {
        return Storage::disk('public')->url($attachment->file_path);
    }
}
