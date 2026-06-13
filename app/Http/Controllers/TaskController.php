<?php

namespace App\Http\Controllers;

use App\Enums\EvidenceType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskComment;
use App\Models\TaskEvidence;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(private TaskService $taskService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();
        $query = Task::with(['assignee:id,name', 'creator:id,name', 'evidences'])
            ->where(fn ($q) => $q
                ->where('creator_id', $user->id)
                ->orWhere('assignee_id', $user->id)
            )
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'ilike', "%{$s}%"))
            ->orderByDesc('created_at');

        $allTasks = $query->paginate(50);
        $tasksByStatus = $query->get()->groupBy(fn ($t) => $t->status->value);

        $users = User::where('organization_id', $user->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tasks/Index', [
            'tasks_by_status' => $tasksByStatus,
            'all_tasks'       => [
                'data' => $allTasks->items(),
                'meta' => ['total' => $allTasks->total()],
            ],
            'filters' => $request->only(['status', 'priority', 'search']),
            'users'   => $users,
        ]);
    }

    public function show(Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'creator:id,name',
            'assignee:id,name',
            'reviewer:id,name',
            'checklists',
            'evidences',
            'comments.user:id,name',
            'statusHistories',
        ]);

        $user = auth()->user();

        return Inertia::render('Tasks/Show', [
            'task' => $task,
            'can'  => [
                'update'     => $user->can('update', $task),
                'delete'     => $user->can('delete', $task),
                'transition' => $user->can('update', $task),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'priority'    => ['required', 'in:critical,high,medium,low,routine'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date'    => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $this->taskService->create($data, $request->user());

        return redirect()->route('tasks.index')->with('success', 'Task berhasil dibuat.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'priority'    => ['sometimes', 'in:critical,high,medium,low,routine'],
            'assignee_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $this->taskService->update($task, $data);

        return back()->with('success', 'Task berhasil diperbarui.');
    }

    public function transition(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $newStatus = TaskStatus::from($data['status']);
        $this->taskService->transitionStatus($task, $newStatus, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Status task berhasil diubah.');
    }

    public function addComment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        return back()->with('success', 'Komentar berhasil ditambahkan.');
    }

    public function addChecklist(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
        ]);

        $maxOrder = $task->checklists()->max('sort_order') ?? 0;

        TaskChecklist::create([
            'task_id'    => $task->id,
            'title'      => $data['title'],
            'is_done'    => false,
            'sort_order' => $maxOrder + 1,
        ]);

        return back();
    }

    public function toggleChecklist(Request $request, TaskChecklist $checklist): RedirectResponse
    {
        $this->authorize('update', $checklist->task);

        $checklist->update([
            'is_done' => ! $checklist->is_done,
            'done_by' => $checklist->is_done ? null : $request->user()->id,
            'done_at' => $checklist->is_done ? null : now(),
        ]);

        return back();
    }

    public function deleteChecklist(TaskChecklist $checklist): RedirectResponse
    {
        $this->authorize('update', $checklist->task);
        $checklist->delete();
        return back();
    }

    public function addEvidence(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title'  => ['required', 'string', 'max:255'],
            'file'   => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        $this->taskService->addEvidence(
            $task,
            [
                'title'         => $data['title'],
                'evidence_type' => EvidenceType::FILE->value,
            ],
            $request->file('file'),
            $request->user()
        );

        return back()->with('success', 'Bukti berhasil diupload.');
    }

    public function deleteEvidence(TaskEvidence $evidence): RedirectResponse
    {
        $this->authorize('update', $evidence->task);
        $evidence->delete();
        return back()->with('success', 'Bukti berhasil dihapus.');
    }
}
