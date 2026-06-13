<?php

namespace App\Http\Controllers;

use App\Enums\CalendarType;
use App\Models\CalendarEvent;
use App\Services\CalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(private CalendarService $calendarService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $user        = $request->user();
        $monthParam  = $request->query('month', now()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth();
        $start       = $currentMonth->copy()->startOfWeek(Carbon::MONDAY);
        $end         = $currentMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $events = $this->calendarService->feedForUser($user, $start, $end);

        return Inertia::render('Calendar/Index', [
            'events'       => $events,
            'currentMonth' => $currentMonth->format('Y-m'),
            'view'         => $request->query('view', 'month'),
            'filters'      => $request->only(['month', 'view']),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $user  = $request->user();
        $start = Carbon::parse($request->query('start', now()->startOfMonth()));
        $end   = Carbon::parse($request->query('end', now()->endOfMonth()));

        $events = $this->calendarService->feedForUser($user, $start, $end);

        return response()->json(['events' => $events]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CalendarEvent::class);

        $typeValues = array_column(CalendarType::cases(), 'value');

        $data = $request->validate([
            'title'         => ['required', 'string', 'max:500'],
            'description'   => ['nullable', 'string'],
            'calendar_type' => ['required', Rule::in($typeValues)],
            'location'      => ['nullable', 'string', 'max:500'],
            'start_at'      => ['required', 'date'],
            'end_at'        => ['required', 'date', 'after_or_equal:start_at'],
            'all_day'       => ['boolean'],
            'color'         => ['nullable', 'string', 'size:7'],
            'is_public'     => ['boolean'],
        ]);

        $this->calendarService->create($data, $request->user());

        return back()->with('success', 'Acara berhasil dibuat.');
    }

    public function update(Request $request, CalendarEvent $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $typeValues = array_column(CalendarType::cases(), 'value');

        $data = $request->validate([
            'title'         => ['sometimes', 'string', 'max:500'],
            'description'   => ['nullable', 'string'],
            'calendar_type' => ['sometimes', Rule::in($typeValues)],
            'location'      => ['nullable', 'string', 'max:500'],
            'start_at'      => ['sometimes', 'date'],
            'end_at'        => ['sometimes', 'date', 'after_or_equal:start_at'],
            'all_day'       => ['boolean'],
            'color'         => ['nullable', 'string', 'size:7'],
            'is_public'     => ['boolean'],
        ]);

        $this->calendarService->update($event, $data);

        return back()->with('success', 'Acara berhasil diperbarui.');
    }

    public function destroy(CalendarEvent $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $this->calendarService->delete($event, auth()->user());

        return redirect()->route('calendar.index')
            ->with('success', 'Acara berhasil dihapus.');
    }
}
