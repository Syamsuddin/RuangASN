<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()
                    ? new UserResource($request->user()->load('organization', 'roles'))
                    : null,
                'permissions' => fn() => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')
                    : [],
                'roles' => fn() => $request->user()
                    ? $request->user()->getRoleNames()
                    : [],
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
            ],
            'notifications' => fn() => $request->user() ? [
                'unread_count' => AppNotification::where('recipient_id', $request->user()->id)
                    ->whereNull('read_at')
                    ->count(),
                'recent' => AppNotification::where('recipient_id', $request->user()->id)
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get(['id', 'title', 'body', 'notification_type', 'read_at', 'created_at', 'data'])
                    ->toArray(),
            ] : null,
        ]);
    }
}
