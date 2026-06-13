<?php
namespace App\Services\Ai\Providers;

use App\Enums\AiIntent;
use App\Enums\ProposedActionType;
use App\Services\Ai\AiResult;
use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Str;

/**
 * Deterministic, always-available provider used in dev/test and as the
 * terminal fallback. No randomness, no time-dependence — given the same
 * messages + options it always returns the same result.
 */
class FakeAiProvider implements AiProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AiResult
    {
        $lastUser = $this->lastUserMessage($messages);
        $intent   = isset($options['intent']) && $options['intent'] instanceof AiIntent
            ? $options['intent']
            : null;

        $content = $this->reply($lastUser, $intent);
        $actions = $this->proposedActions($lastUser, $intent);

        // Deterministic token counts derived from text length (no randomness).
        $promptTokens     = (int) ceil(mb_strlen($this->concat($messages)) / 4);
        $completionTokens = (int) ceil(mb_strlen($content) / 4);

        return new AiResult(
            content: $content,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            modelName: 'fake-deterministic-v1',
            finishReason: 'stop',
            proposedActions: $actions,
            citations: [],
        );
    }

    private function reply(string $lastUser, ?AiIntent $intent): string
    {
        $trimmed = Str::limit(trim($lastUser), 120, '');

        return match ($intent) {
            AiIntent::CREATE_TASK       => "Baik, saya siapkan usulan tugas berdasarkan permintaan Anda: \"{$trimmed}\". Silakan konfirmasi untuk membuat tugas tersebut.",
            AiIntent::SCHEDULE_MEETING  => "Saya siapkan usulan penjadwalan rapat dari permintaan: \"{$trimmed}\". Konfirmasi untuk menjadwalkan.",
            AiIntent::GENERATE_REPORT   => "Saya siapkan usulan draft laporan terkait: \"{$trimmed}\". Konfirmasi untuk membuat draft laporan.",
            AiIntent::SUMMARIZE_MEETING => "Berikut ringkasan yang saya siapkan untuk: \"{$trimmed}\".",
            AiIntent::KNOWLEDGE_QA      => "Berdasarkan basis pengetahuan organisasi, berikut jawaban untuk: \"{$trimmed}\".",
            AiIntent::PERFORMANCE_QUERY => "Berikut ringkasan kinerja terkait permintaan: \"{$trimmed}\".",
            default                     => "Halo, saya asisten RuangASN. Anda menulis: \"{$trimmed}\". Ada yang bisa saya bantu?",
        };
    }

    /**
     * Maps an intent to a structured proposed action (stored, NOT executed).
     *
     * @return array<int, array<string, mixed>>
     */
    private function proposedActions(string $lastUser, ?AiIntent $intent): array
    {
        $title = $this->deriveTitle($lastUser);

        return match ($intent) {
            AiIntent::CREATE_TASK => [[
                'type'    => ProposedActionType::CREATE_TASK->value,
                'payload' => ['title' => $title, 'priority' => 'medium'],
            ]],
            AiIntent::SCHEDULE_MEETING => [[
                'type'    => ProposedActionType::SCHEDULE_MEETING->value,
                'payload' => ['title' => $title, 'duration_minutes' => 60],
            ]],
            AiIntent::GENERATE_REPORT => [[
                'type'    => ProposedActionType::GENERATE_REPORT_DRAFT->value,
                'payload' => ['title' => $title, 'report_type' => 'activity', 'period_type' => 'monthly'],
            ]],
            default => [],
        };
    }

    private function deriveTitle(string $lastUser): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $lastUser) ?? '');
        $clean = (string) preg_replace(
            '/^(tolong\s+|mohon\s+|buatkan\s+|buat\s+|jadwalkan\s+|jadwal\s+|generate\s+|bikin\s+)+/i',
            '',
            $clean
        );
        $title = Str::limit($clean, 200, '');

        return $title !== '' ? Str::ucfirst($title) : 'Tugas baru dari asisten AI';
    }

    /** @param array<int, array<string, mixed>> $messages */
    private function lastUserMessage(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                return (string) ($messages[$i]['content'] ?? '');
            }
        }

        return '';
    }

    /** @param array<int, array<string, mixed>> $messages */
    private function concat(array $messages): string
    {
        return implode("\n", array_map(
            static fn ($m) => (string) ($m['content'] ?? ''),
            $messages
        ));
    }
}
