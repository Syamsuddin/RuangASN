<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $taskQuery = Task::query()
            ->where(fn($q) => $q
                ->where('creator_id', $user->id)
                ->orWhere('assignee_id', $user->id)
            );

        $taskStats = [
            'total'       => (clone $taskQuery)->count(),
            'in_progress' => (clone $taskQuery)->where('status', 'in_progress')->count(),
            'due_today'   => (clone $taskQuery)->whereDate('due_date', today())->count(),
            'overdue'     => (clone $taskQuery)
                ->where('due_date', '<', now())
                ->whereNotIn('status', ['completed', 'cancelled', 'closed'])
                ->count(),
        ];

        $recentTasks = (clone $taskQuery)
            ->with('assignee:id,name')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'title', 'status', 'priority', 'due_date', 'assignee_id']);

        // Today's meetings
        $todayMeetings = \App\Models\Meeting::query()
            ->where(fn ($q) => $q
                ->where('host_id', $user->id)
                ->orWhere('secretary_id', $user->id)
                ->orWhereHas('participants', fn ($pq) => $pq->where('user_id', $user->id))
            )
            ->whereDate('scheduled_at', today())
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get(['id', 'title', 'scheduled_at', 'duration_minutes', 'meeting_mode', 'location', 'online_url', 'status'])
            ->map(fn ($m) => [
                'id'         => $m->id,
                'title'      => $m->title,
                'start_time' => $m->scheduled_at?->format('H:i'),
                'end_time'   => $m->scheduled_at?->copy()->addMinutes($m->duration_minutes ?? 60)->format('H:i'),
                'mode'       => $m->meeting_mode->value,
                'location'   => $m->location,
                'link'       => $m->online_url,
                'status'     => $m->status->value,
            ])
            ->all();

        return Inertia::render('Dashboard', [
            'taskStats'     => $taskStats,
            'recentTasks'   => $recentTasks,
            'todayMeetings' => $todayMeetings,
        ]);
    }
}
