<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $httpRequest)
    {
        $notifications = Notification::where('user_id', $httpRequest->user()->id)
            ->when($httpRequest->filled('is_read'), fn ($q) => $q->where('is_read', $httpRequest->boolean('is_read')))
            ->latest()
            ->paginate($httpRequest->integer('per_page', 15));

        return NotificationResource::collection($notifications);
    }

    public function markRead(Request $httpRequest, Notification $notification)
    {
        abort_unless($notification->user_id === $httpRequest->user()->id, 403);

        $notification->update(['is_read' => true]);

        return new NotificationResource($notification);
    }

    public function markAllRead(Request $httpRequest)
    {
        Notification::where('user_id', $httpRequest->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->noContent();
    }
}
