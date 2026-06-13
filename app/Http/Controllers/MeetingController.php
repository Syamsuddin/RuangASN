<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\MeetingMode;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Services\MeetingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function __construct(private MeetingService $meetingService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Meeting::class);

        $user = $request->user();

        $query = Meeting::with(['host:id,name', 'participants.user:id,name'])
            ->where(fn ($q) => $q
                ->where('host_id', $user->id)
                ->orWhere('secretary_id', $user->id)
                ->orWhereHas('participants', fn ($pq) => $pq->where('user_id', $user->id))
            )
            ->when($request->segment, function ($q, $seg) {
                return match ($seg) {
                    'today'    => $q->whereDate('scheduled_at', today()),
                    'upcoming' => $q->where('scheduled_at', '>', now())->whereNotIn('status', ['completed', 'cancelled', 'archived']),
                    'past'     => $q->whereIn('status', ['completed', 'archived'])->orWhere('scheduled_at', '<', now()),
                    default    => $q,
                };
            })
            ->orderBy('scheduled_at');

        $meetings = $query->get();

        $users = User::where('organization_id', $user->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Meetings/Index', [
            'meetings' => $meetings->map(fn ($m) => $this->formatMeetingCard($m)),
            'filters'  => $request->only(['segment']),
            'users'    => $users,
        ]);
    }

    public function show(Meeting $meeting): Response
    {
        $this->authorize('view', $meeting);

        $meeting->load([
            'host:id,name',
            'secretary:id,name',
            'participants.user:id,name',
            'agendaItems.presenter:id,name',
            'decisions.recorder:id,name',
            'decisions.agendaItem:id,title',
            'actionItems.assignee:id,name',
            'minutes',
        ]);

        $user = auth()->user();

        return Inertia::render('Meetings/Show', [
            'meeting' => $meeting,
            'can'     => [
                'update'           => $user->can('update', $meeting),
                'recordMinutes'    => $user->can('recordMinutes', $meeting),
                'approveMinutes'   => $user->can('approveMinutes', Meeting::class),
                'createActionItem' => $user->can('createActionItem', $meeting),
                'transition'       => $user->can('update', $meeting),
            ],
            'users' => User::where('organization_id', $user->organization_id)
                ->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Meeting::class);

        $modeValues = array_column(MeetingMode::cases(), 'value');
        $typeValues = array_column(MeetingType::cases(), 'value');

        $data = $request->validate([
            'title'            => ['required', 'string', 'max:500'],
            'description'      => ['nullable', 'string'],
            'meeting_type'     => ['required', Rule::in($typeValues)],
            'meeting_mode'     => ['required', Rule::in($modeValues)],
            'scheduled_at'     => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15'],
            'location'         => ['nullable', 'string', 'max:500'],
            'online_url'       => ['nullable', 'url', 'max:2000'],
            'secretary_id'     => ['nullable', 'exists:users,id'],
            'participant_ids'  => ['nullable', 'array'],
            'participant_ids.*' => ['exists:users,id'],
        ]);

        $participantIds = $data['participant_ids'] ?? [];
        unset($data['participant_ids']);

        $meeting = $this->meetingService->create($data, $request->user());

        foreach ($participantIds as $userId) {
            if ($userId !== $request->user()->id) {
                $this->meetingService->addParticipant($meeting, ['user_id' => $userId]);
            }
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Meeting berhasil dijadwalkan.');
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $data = $request->validate([
            'title'            => ['sometimes', 'string', 'max:500'],
            'description'      => ['nullable', 'string'],
            'scheduled_at'     => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15'],
            'location'         => ['nullable', 'string', 'max:500'],
            'online_url'       => ['nullable', 'url', 'max:2000'],
            'agenda_notes'     => ['nullable', 'string'],
            'secretary_id'     => ['nullable', 'exists:users,id'],
        ]);

        $this->meetingService->update($meeting, $data);

        return back()->with('success', 'Meeting berhasil diperbarui.');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        $this->authorize('cancel', $meeting);

        $meeting->update(['deleted_by' => auth()->id()]);
        $meeting->delete();

        return redirect()->route('meetings.index')
            ->with('success', 'Meeting berhasil dihapus.');
    }

    public function transition(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $statusValues = array_column(MeetingStatus::cases(), 'value');

        $data = $request->validate([
            'status' => ['required', Rule::in($statusValues)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $newStatus = MeetingStatus::from($data['status']);
        $this->meetingService->transitionStatus($meeting, $newStatus, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Status meeting berhasil diubah.');
    }

    public function addParticipant(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['nullable', 'string', 'in:host,secretary,presenter,participant,optional'],
        ]);

        $this->meetingService->addParticipant($meeting, $data);

        return back()->with('success', 'Peserta berhasil ditambahkan.');
    }

    public function recordAttendance(Request $request, MeetingParticipant $participant): RedirectResponse
    {
        $this->authorize('update', $participant->meeting);

        $data = $request->validate([
            'attendance_status' => ['required', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
        ]);

        $status = AttendanceStatus::from($data['attendance_status']);
        $this->meetingService->recordAttendance($participant, $status);

        return back()->with('success', 'Status kehadiran berhasil dicatat.');
    }

    public function addAgendaItem(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $data = $request->validate([
            'title'            => ['required', 'string', 'max:500'],
            'description'      => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'presenter_id'     => ['nullable', 'exists:users,id'],
        ]);

        $this->meetingService->addAgendaItem($meeting, $data);

        return back()->with('success', 'Agenda berhasil ditambahkan.');
    }

    public function addDecision(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('recordMinutes', $meeting);

        $data = $request->validate([
            'content'        => ['required', 'string'],
            'agenda_item_id' => ['nullable', 'exists:meeting_agenda_items,id'],
        ]);

        $this->meetingService->addDecision($meeting, $data, $request->user());

        return back()->with('success', 'Keputusan berhasil dicatat.');
    }

    public function addActionItem(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('createActionItem', $meeting);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date'    => ['nullable', 'date'],
            'decision_id' => ['nullable', 'exists:meeting_decisions,id'],
            'create_task' => ['nullable', 'boolean'],
        ]);

        $this->meetingService->addActionItem($meeting, $data, $request->user());

        return back()->with('success', 'Action item berhasil ditambahkan.');
    }

    public function upsertMinutes(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('recordMinutes', $meeting);

        $data = $request->validate([
            'content'  => ['nullable', 'string'],
            'ai_draft' => ['nullable', 'string'],
            'status'   => ['nullable', 'string', 'in:draft,final'],
        ]);

        $this->meetingService->upsertMinutes($meeting, $data, $request->user());

        return back()->with('success', 'Notulensi berhasil disimpan.');
    }

    public function approveMinutes(Request $request, MeetingMinute $minutes): RedirectResponse
    {
        $this->authorize('approveMinutes', Meeting::class);

        $this->meetingService->approveMinutes($minutes, $request->user());

        return back()->with('success', 'Notulensi berhasil disetujui.');
    }

    private function formatMeetingCard(Meeting $m): array
    {
        /** @var \App\Models\User|null $host */
        $host = $m->host;

        return [
            'id'               => $m->id,
            'title'            => $m->title,
            'status'           => $m->status,
            'meeting_mode'     => $m->meeting_mode,
            'meeting_type'     => $m->meeting_type,
            'scheduled_at'     => $m->scheduled_at?->toISOString(),
            'duration_minutes' => $m->duration_minutes,
            'location'         => $m->location,
            'online_url'       => $m->online_url,
            'host'             => $host ? ['id' => $host->id, 'name' => $host->name] : null,
            'participant_count' => $m->participants->count(),
            'participants'     => $m->participants->take(5)->map(fn ($p) => [
                'id'   => $p->user_id,
                'name' => $p->user?->name ?? '',
            ])->values()->all(),
        ];
    }
}
