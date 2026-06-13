<?php
namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private TaskService $taskService) {}

    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::with(['assignee', 'creator', 'evidences'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->assignee_id, fn ($q, $id) => $q->where('assignee_id', $id))
            ->when($request->due_before, fn ($q, $d) => $q->whereDate('due_date', '<=', $d))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return TaskResource::collection($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'task_type'   => ['required', 'string'],
            'priority'    => ['required', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $task = $this->taskService->create($data, $request->user());
        return response()->json(['data' => new TaskResource($task->load(['assignee', 'creator']))], 201);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);
        return response()->json(['data' => new TaskResource(
            $task->load(['assignee', 'creator', 'reviewer', 'evidences', 'statusHistories', 'checklists', 'comments.user'])
        )]);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'priority'    => ['sometimes', 'string'],
            'assignee_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'due_date'    => ['sometimes', 'nullable', 'date'],
        ]);

        $task = $this->taskService->update($task, $data);
        return response()->json(['data' => new TaskResource($task)]);
    }

    public function transition(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $newStatus = TaskStatus::from($data['status']);
        $task = $this->taskService->transitionStatus($task, $newStatus, $request->user(), $data['reason'] ?? null);
        return response()->json(['data' => new TaskResource($task)]);
    }

    public function addEvidence(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'evidence_type' => ['required', 'string'],
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'url'           => ['required_if:evidence_type,url', 'nullable', 'url'],
            'file'          => ['required_if:evidence_type,file,image,video', 'nullable', 'file', 'max:20480'],
        ]);

        $evidence = $this->taskService->addEvidence($task, $data, $request->file('file'), $request->user());
        return response()->json(['data' => $evidence], 201);
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $this->taskService->delete($task, request()->user());
        return response()->json(null, 204);
    }
}
