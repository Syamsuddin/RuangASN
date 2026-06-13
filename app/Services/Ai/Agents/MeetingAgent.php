<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\MeetingDecision;
use App\Models\MeetingParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Meeting Agent. Given context_type='meeting' + context_id, loads the Meeting
 * (authorized via the user's own 'view' policy) with its agenda items,
 * decisions, and participants, then produces a structured notulensi (minutes)
 * DRAFT for human review (ringkasan, poin agenda, keputusan, tindak lanjut).
 *
 * Deterministic: the draft is assembled purely from the meeting's actual data,
 * no randomness or time-dependence. The draft is returned as assistant content
 * and is NEVER auto-saved (AXIOM-04) — a human edits + saves it.
 */
class MeetingAgent extends BaseAgent implements DraftingAgent
{
    public function type(): AiAgentType
    {
        return AiAgentType::MEETING;
    }

    protected function role(): string
    {
        return 'Anda adalah AI Meeting Agent RuangASN yang menyusun draft notulensi rapat '
            . 'secara terstruktur (ringkasan, poin agenda, keputusan, tindak lanjut) dari data rapat.';
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContext(User $user, array $ctx): array
    {
        $meeting = $this->resolveMeeting($user, $ctx);
        if ($meeting === null) {
            return [];
        }

        return [[
            'role'    => 'system',
            'content' => "Data rapat untuk penyusunan notulensi:\n" . $this->meetingFacts($meeting),
        ]];
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function draft(User $user, string $content, array $ctx): ?string
    {
        $meeting = $this->resolveMeeting($user, $ctx);
        if ($meeting === null) {
            return null;
        }

        return $this->renderMinutes($meeting);
    }

    /**
     * Load the meeting and authorize 'view' through the user's OWN policy.
     * Returns null if no/invalid context; throws AuthorizationException (403)
     * if the user may not view the meeting (no escalation).
     *
     * @param array<string, mixed> $ctx
     */
    private function resolveMeeting(User $user, array $ctx): ?Meeting
    {
        if (($ctx['context_type'] ?? null) !== 'meeting' || empty($ctx['context_id'])) {
            return null;
        }

        /** @var Meeting|null $meeting */
        $meeting = Meeting::query()
            ->with([
                'agendaItems',
                'decisions',
                'participants.user:id,name',
                'host:id,name',
            ])
            ->find($ctx['context_id']);

        if ($meeting === null) {
            return null;
        }

        Gate::forUser($user)->authorize('view', $meeting);

        return $meeting;
    }

    private function meetingFacts(Meeting $meeting): string
    {
        $lines = [
            "Judul: {$meeting->title}",
            'Jadwal: ' . ($meeting->scheduled_at?->toDateTimeString() ?? '-'),
        ];

        /** @var \Illuminate\Database\Eloquent\Collection<int, MeetingAgendaItem> $agendaItems */
        $agendaItems = $meeting->agendaItems;
        /** @var \Illuminate\Database\Eloquent\Collection<int, MeetingDecision> $decisionItems */
        $decisionItems = $meeting->decisions;

        $agenda = $agendaItems
            ->map(fn (MeetingAgendaItem $a) => $a->title)
            ->filter()
            ->values();
        if ($agenda->isNotEmpty()) {
            $lines[] = 'Agenda: ' . $agenda->implode('; ');
        }

        $decisions = $decisionItems
            ->map(fn (MeetingDecision $d) => $d->content)
            ->filter()
            ->values();
        if ($decisions->isNotEmpty()) {
            $lines[] = 'Keputusan: ' . $decisions->implode('; ');
        }

        return implode("\n", $lines);
    }

    /**
     * Render a structured notulensi draft. Deterministic from meeting data.
     */
    private function renderMinutes(Meeting $meeting): string
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, MeetingParticipant> $participants */
        $participants = $meeting->participants;
        /** @var \Illuminate\Database\Eloquent\Collection<int, MeetingAgendaItem> $agendaItems */
        $agendaItems = $meeting->agendaItems;
        /** @var \Illuminate\Database\Eloquent\Collection<int, MeetingDecision> $decisionItems */
        $decisionItems = $meeting->decisions;

        $participantNames = $participants
            ->map(function (MeetingParticipant $p): ?string {
                /** @var \App\Models\User|null $u */
                $u = $p->user;

                return $u?->name;
            })
            ->filter()
            ->values();

        $scheduled = $meeting->scheduled_at?->toDateTimeString() ?? '-';
        $hostName  = $meeting->host()->value('name') ?? '-';

        $out   = [];
        $out[] = '<h2>Notulensi Rapat: ' . e($meeting->title) . '</h2>';
        $out[] = '<p><strong>Waktu:</strong> ' . e($scheduled)
            . ' &middot; <strong>Host:</strong> ' . e($hostName) . '</p>';

        // Ringkasan.
        $out[] = '<h3>Ringkasan</h3>';
        $summary = 'Rapat "' . e($meeting->title) . '" dihadiri ' . $participantNames->count()
            . ' peserta dengan ' . $agendaItems->count() . ' poin agenda dan '
            . $decisionItems->count() . ' keputusan yang dicatat.';
        $out[] = '<p>' . $summary . '</p>';

        // Poin agenda.
        $out[] = '<h3>Poin Agenda</h3>';
        if ($agendaItems->isNotEmpty()) {
            $items = $agendaItems
                ->map(fn (MeetingAgendaItem $a) => '<li>' . e((string) $a->title)
                    . ($a->description ? ' — ' . e((string) $a->description) : '') . '</li>')
                ->implode('');
            $out[] = '<ol>' . $items . '</ol>';
        } else {
            $out[] = '<p><em>Tidak ada poin agenda yang dicatat.</em></p>';
        }

        // Keputusan.
        $out[] = '<h3>Keputusan</h3>';
        if ($decisionItems->isNotEmpty()) {
            $items = $decisionItems
                ->map(fn (MeetingDecision $d) => '<li>' . e((string) $d->content) . '</li>')
                ->implode('');
            $out[] = '<ol>' . $items . '</ol>';
        } else {
            $out[] = '<p><em>Tidak ada keputusan yang dicatat.</em></p>';
        }

        // Tindak lanjut.
        $out[] = '<h3>Tindak Lanjut</h3>';
        $out[] = '<p>Tindak lanjut atas keputusan di atas disusun sebagai action item dan '
            . 'ditugaskan kepada peserta terkait untuk dipantau penyelesaiannya.</p>';

        if ($participantNames->isNotEmpty()) {
            $out[] = '<h3>Peserta</h3>';
            $out[] = '<p>' . e($participantNames->implode(', ')) . '</p>';
        }

        return implode("\n", $out);
    }
}
