<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = AppNotification::where('recipient_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $unreadCount = AppNotification::where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->recipient_id === $request->user()->id, 403);

        $notification->update(['read_at' => now(), 'status' => 'read']);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        AppNotification::where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'status' => 'read']);

        return back()->with('success', 'Semua notifikasi telah ditandai dibaca.');
    }

    public function destroy(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->recipient_id === $request->user()->id, 403);

        $notification->delete();

        return back()->with('success', 'Notifikasi dihapus.');
    }

    public function preferences(Request $request): Response
    {
        $preference = NotificationPreference::firstOrNew(
            ['user_id' => $request->user()->id],
            [
                'in_app'            => true,
                'email'             => false,
                'push'              => false,
                'whatsapp'          => false,
                'task_assigned'     => true,
                'task_due'          => true,
                'meeting_invited'   => true,
                'document_approval' => true,
                'report_status'     => true,
                'digest_frequency'  => 'realtime',
            ]
        );

        return Inertia::render('Notifications/Preferences', [
            'preference' => $preference,
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'in_app'            => ['boolean'],
            'email'             => ['boolean'],
            'push'              => ['boolean'],
            'whatsapp'          => ['boolean'],
            'task_assigned'     => ['boolean'],
            'task_due'          => ['boolean'],
            'meeting_invited'   => ['boolean'],
            'document_approval' => ['boolean'],
            'report_status'     => ['boolean'],
            'digest_frequency'  => ['in:realtime,daily,off'],
        ]);

        NotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            array_merge($data, ['user_id' => $request->user()->id])
        );

        return back()->with('success', 'Preferensi notifikasi berhasil disimpan.');
    }
}
