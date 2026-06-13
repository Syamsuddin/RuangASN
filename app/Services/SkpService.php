<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\PerformanceStatus;
use App\Models\SkpIndicator;
use App\Models\SkpPeriod;
use App\Models\SkpPlan;
use App\Models\SkpRealization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkpService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
    ) {}

    /** Create a new SKP plan for a user in a period. Guard UNIQUE(user,period). */
    public function createPlan(array $data, User $author): SkpPlan
    {
        return DB::transaction(function () use ($data, $author) {
            $exists = SkpPlan::withoutGlobalScopes()
                ->where('user_id', $author->id)
                ->where('period_id', $data['period_id'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'period_id' => 'SKP untuk periode ini sudah ada.',
                ]);
            }

            $plan = SkpPlan::create([
                'organization_id' => $author->organization_id,
                'user_id'         => $author->id,
                'period_id'       => $data['period_id'],
                'superior_id'     => $data['superior_id'] ?? null,
                'status'          => PerformanceStatus::PLANNING->value,
                'created_by'      => $author->id,
            ]);

            $this->outbox->publish('skp.created', [
                'skp_plan_id'    => $plan->id,
                'organization_id'=> $plan->organization_id,
                'user_id'        => $author->id,
            ], 'SkpPlan', $plan->id);

            return $plan->fresh();
        });
    }

    /** Update mutable plan fields (only while planning). */
    public function updatePlan(SkpPlan $plan, array $data): SkpPlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->update($data);

            $this->outbox->publish('skp.updated', [
                'skp_plan_id'    => $plan->id,
                'organization_id'=> $plan->organization_id,
            ], 'SkpPlan', $plan->id);

            return $plan->fresh();
        });
    }

    /**
     * Soft-delete a plan with full audit trail.
     * Records deleted_by, soft-deletes, publishes skp.deleted outbox event,
     * and writes a DELETED audit log entry.
     */
    public function deletePlan(SkpPlan $plan, User $actor): void
    {
        DB::transaction(function () use ($plan, $actor) {
            $planId = $plan->id;
            $orgId  = $plan->organization_id;

            $plan->update(['deleted_by' => $actor->id]);
            $plan->delete();

            $this->outbox->publish('skp.deleted', [
                'skp_plan_id'     => $planId,
                'organization_id' => $orgId,
                'deleted_by'      => $actor->id,
            ], 'SkpPlan', $planId);

            $this->audit->log(
                AuditAction::DELETED,
                'SkpPlan',
                $planId,
                [],
                ['deleted_by' => $actor->id],
            );
        });
    }

    /** Add an indicator to a plan. */
    public function addIndicator(SkpPlan $plan, array $data): SkpIndicator
    {
        return DB::transaction(function () use ($plan, $data) {
            /** @var SkpIndicator $indicator */
            $indicator = SkpIndicator::create([
                'skp_plan_id'         => $plan->id,
                'parent_indicator_id' => $data['parent_indicator_id'] ?? null,
                'perspective'         => $data['perspective'],
                'name'                => $data['name'],
                'target_value'        => $data['target_value'],
                'target_unit'         => $data['target_unit'],
                'weight'              => $data['weight'] ?? 100,
                'superior_expectation'=> $data['superior_expectation'] ?? null,
                'sort_order'          => $data['sort_order'] ?? 0,
            ]);

            return $indicator->fresh();
        });
    }

    /** Update an indicator. */
    public function updateIndicator(SkpIndicator $indicator, array $data): SkpIndicator
    {
        return DB::transaction(function () use ($indicator, $data) {
            $indicator->update($data);

            return $indicator->fresh();
        });
    }

    /**
     * Soft-delete an indicator (only allowed when plan is in planning status).
     * SkpIndicator uses SoftDeletes, so ->delete() sets deleted_at rather than
     * hard-removing the row (Architecture Invariant #4 — soft delete only).
     */
    public function deleteIndicator(SkpIndicator $indicator): void
    {
        DB::transaction(function () use ($indicator) {
            $indicator->delete();
        });
    }

    /**
     * Submit a plan (sets submitted_at timestamp).
     * Plan stays in planning until superior approves.
     */
    public function submitPlan(SkpPlan $plan, User $actor): SkpPlan
    {
        return DB::transaction(function () use ($plan, $actor) {
            $plan->update(['submitted_at' => now()]);

            $this->outbox->publish('skp.submitted', [
                'skp_plan_id'    => $plan->id,
                'organization_id'=> $plan->organization_id,
                'submitted_by'   => $actor->id,
            ], 'SkpPlan', $plan->id);

            return $plan->fresh();
        });
    }

    /**
     * Approve a plan — transitions planning → active.
     * Guard: approver must be the superior_id or have performance.skp.approve.
     */
    public function approvePlan(SkpPlan $plan, User $approver): SkpPlan
    {
        return DB::transaction(function () use ($plan, $approver) {
            $isSuperior = $plan->superior_id === $approver->id;
            $hasGlobalApprove = $approver->hasPermissionTo('performance.skp.approve');

            if (! $isSuperior && ! $hasGlobalApprove) {
                throw ValidationException::withMessages([
                    'approved_by' => 'Hanya atasan langsung yang dapat menyetujui SKP ini.',
                ]);
            }

            $this->transition($plan, PerformanceStatus::ACTIVE, $approver);

            $plan->update([
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $this->audit->log(
                AuditAction::APPROVED,
                'SkpPlan',
                $plan->id,
                [],
                ['approved_by' => $approver->id],
            );

            return $plan->fresh();
        });
    }

    /**
     * Add a realization entry for an indicator.
     * Recomputes indicator realization_value (SUM) and achievement_pct after insert.
     */
    public function addRealization(SkpIndicator $indicator, array $data, User $actor): SkpRealization
    {
        return DB::transaction(function () use ($indicator, $data, $actor) {
            /** @var SkpRealization $realization */
            $realization = SkpRealization::create([
                'indicator_id'      => $indicator->id,
                'user_id'           => $actor->id,
                'realization_value' => $data['realization_value'],
                'realization_date'  => $data['realization_date'],
                'description'       => $data['description'] ?? null,
                'task_id'           => $data['task_id'] ?? null,
                'document_id'       => $data['document_id'] ?? null,
                'created_by'        => $actor->id,
            ]);

            // Recompute SUM-based realization and achievement on the indicator
            $indicator->recomputeAchievement();

            /** @var SkpPlan|null $indicatorPlan */
            $indicatorPlan = SkpPlan::find($indicator->skp_plan_id);

            $this->outbox->publish('skp.realization.added', [
                'indicator_id'   => $indicator->id,
                'skp_plan_id'    => $indicator->skp_plan_id,
                'organization_id'=> $indicatorPlan?->organization_id,
                'added_by'       => $actor->id,
            ], 'SkpIndicator', $indicator->id);

            return $realization->fresh();
        });
    }

    /**
     * Generic status transition with state machine guard.
     */
    public function transition(SkpPlan $plan, PerformanceStatus $new, User $actor): SkpPlan
    {
        return DB::transaction(function () use ($plan, $new, $actor) {
            if (! $plan->canTransitionTo($new)) {
                throw ValidationException::withMessages([
                    'status' => "Tidak dapat beralih dari status {$plan->status->value} ke {$new->value}.",
                ]);
            }

            $from = $plan->status->value;
            $plan->update(['status' => $new->value]);

            $this->outbox->publish('skp.status_changed', [
                'skp_plan_id'    => $plan->id,
                'organization_id'=> $plan->organization_id,
                'from_status'    => $from,
                'to_status'      => $new->value,
                'changed_by'     => $actor->id,
            ], 'SkpPlan', $plan->id);

            $this->audit->log(
                AuditAction::STATUS_CHANGED,
                'SkpPlan',
                $plan->id,
                ['status' => $from],
                ['status' => $new->value],
            );

            return $plan->fresh();
        });
    }

    /** Create or update an SKP period. */
    public function createPeriod(array $data, User $actor): SkpPeriod
    {
        return DB::transaction(function () use ($data, $actor) {
            /** @var SkpPeriod $period */
            $period = SkpPeriod::create([
                'organization_id' => $actor->organization_id,
                'year'            => $data['year'],
                'semester'        => $data['semester'] ?? null,
                'name'            => $data['name'],
                'start_date'      => $data['start_date'],
                'end_date'        => $data['end_date'],
                'is_active'       => $data['is_active'] ?? true,
            ]);

            return $period->fresh();
        });
    }
}
