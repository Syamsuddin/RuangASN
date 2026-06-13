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
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'title', 'status', 'priority', 'due_date', 'assignee_id']);

        return Inertia::render('Dashboard', [
            'taskStats'   => $taskStats,
            'recentTasks' => $recentTasks,
        ]);
    }
}
