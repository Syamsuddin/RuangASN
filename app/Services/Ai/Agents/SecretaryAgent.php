<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;
use App\Enums\ProposedActionType;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Task/schedule helper. Proposes create_task / schedule_meeting /
 * add_calendar_event from the user's request. Deterministic: the action type
 * is chosen by intent-style keyword matching on the request (no randomness).
 *
 * Proposed actions are STORED ONLY — execution happens through
 * confirmAction() under the user's own policies.
 */
class SecretaryAgent extends BaseAgent
{
    public function type(): AiAgentType
    {
        return AiAgentType::SECRETARY;
    }

    protected function role(): string
    {
        return 'Anda adalah AI Secretary RuangASN yang membantu pegawai ASN mengelola tugas, '
            . 'jadwal, dan rapat. Bantu menyusun usulan tindakan yang ringkas dan jelas.';
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<int, array{type: string, payload: array<string, mixed>}>
     */
    public function proposeActions(User $user, string $content, array $ctx): array
    {
        $q     = ' ' . mb_strtolower(trim($content)) . ' ';
        $title = $this->deriveTitle($content);

        // Schedule a meeting (rapat/meeting/pertemuan/jadwalkan rapat).
        if ($this->has($q, ['jadwalkan rapat', 'atur rapat', 'jadwal rapat', 'rapat', 'meeting', 'pertemuan'])) {
            return [[
                'type'    => ProposedActionType::SCHEDULE_MEETING->value,
                'payload' => ['title' => $title, 'duration_minutes' => 60],
            ]];
        }

        // Add a calendar event (agenda/acara/kalender/ingatkan).
        if ($this->has($q, ['agenda', 'acara', 'kalender', 'calendar', 'ingatkan', 'reminder', 'event'])) {
            return [[
                'type'    => ProposedActionType::ADD_CALENDAR_EVENT->value,
                'payload' => ['title' => $title, 'calendar_type' => 'personal'],
            ]];
        }

        // Default secretary action: create a task.
        return [[
            'type'    => ProposedActionType::CREATE_TASK->value,
            'payload' => ['title' => $title, 'priority' => 'medium'],
        ]];
    }

    private function deriveTitle(string $content): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', $content));
        $clean = (string) preg_replace(
            '/^(tolong\s+|mohon\s+|buatkan\s+|buat\s+|jadwalkan\s+|jadwal\s+|atur\s+|generate\s+|bikin\s+|tambah\s+|tambahkan\s+|ingatkan\s+)+/i',
            '',
            $clean
        );
        $title = Str::limit($clean, 200, '');

        return $title !== '' ? Str::ucfirst($title) : 'Tugas baru dari asisten AI';
    }

    /** @param array<int, string> $needles */
    private function has(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }
}
