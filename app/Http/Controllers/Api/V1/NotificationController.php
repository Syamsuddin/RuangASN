<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifs = AppNotification::where('recipient_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json(['data' => $notifs]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->recipient_id === $request->user()->id, 403);

        $notification->update(['read_at' => now(), 'status' => 'read']);
        return response()->json(['message' => 'Notifikasi ditandai dibaca.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'status' => 'read']);

        return response()->json(['message' => 'Semua notifikasi ditandai dibaca.']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = AppNotification::where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['data' => ['count' => $count]]);
    }
}
