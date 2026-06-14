<?php
namespace App\Services\Ai;

use App\Enums\AiAgentType;
use App\Enums\AiIntent;

/**
 * Deterministic, rule-based intent classifier (Indonesian keywords). Order
 * matters: more specific intents are tested first. A real LLM-based router is
 * future work; rule-based keeps Phase 3 deterministic + testable.
 */
class IntentClassifier
{
    /**
     * @return array{intent: AiIntent, agent: AiAgentType}
     */
    public function classify(string $query): array
    {
        $q = ' ' . mb_strtolower(trim($query)) . ' ';

        // ── Executive brief (checked early: "ringkasan eksekutif" / "dashboard
        //    pimpinan" are specific phrases that must NOT fall into the generic
        //    summarize/meeting branch). ──────────────────────────────────────
        if ($this->matches($q, ['ringkasan eksekutif', 'eksekutif', 'dashboard pimpinan', 'brief eksekutif', 'ringkasan pimpinan'])) {
            return ['intent' => AiIntent::EXECUTIVE_BRIEF, 'agent' => AiAgentType::EXECUTIVE];
        }

        // ── Workload analysis ───────────────────────────────────────────────
        if ($this->matches($q, ['beban kerja', 'workload', 'distribusi tugas', 'beban tugas'])) {
            return ['intent' => AiIntent::WORKLOAD_QUERY, 'agent' => AiAgentType::WORKLOAD];
        }

        // ── Performance / SKP (checked FIRST: these keywords are highly
        //    specific, so a mixed-domain query like "capaian SKP dari rapat
        //    kemarin" routes to PERFORMANCE rather than the generic meeting
        //    branch) — L2. ────────────────────────────────────────────────────
        if ($this->matches($q, ['skp', 'kinerja', 'predikat', 'realisasi', 'capaian', 'indikator'])) {
            return ['intent' => AiIntent::PERFORMANCE_QUERY, 'agent' => AiAgentType::PERFORMANCE];
        }

        // ── Summarize/notulen meeting (before generic "rapat") ──────────────
        if ($this->matches($q, ['notulen', 'ringkas rapat', 'rangkum rapat', 'rangkum', 'ringkasan rapat'])) {
            return ['intent' => AiIntent::SUMMARIZE_MEETING, 'agent' => AiAgentType::MEETING];
        }

        // ── Schedule meeting ────────────────────────────────────────────────
        if ($this->matches($q, ['jadwalkan', 'jadwal rapat', 'jadwalkan rapat', 'atur rapat', 'rapat', 'meeting', 'pertemuan'])) {
            return ['intent' => AiIntent::SCHEDULE_MEETING, 'agent' => AiAgentType::SECRETARY];
        }

        // ── Create task (an explicit "tugas/task" verb wins over a topical
        //    mention of "laporan", e.g. "buatkan tugas menyusun laporan ...") ─
        if ($this->matches($q, ['buat tugas', 'buatkan tugas', 'buat task', 'buatkan task', 'tambah tugas', 'tugas baru', 'tugas', 'task'])) {
            return ['intent' => AiIntent::CREATE_TASK, 'agent' => AiAgentType::SECRETARY];
        }

        // ── Report draft ────────────────────────────────────────────────────
        if ($this->matches($q, ['buat laporan', 'buatkan laporan', 'draft laporan', 'generate laporan', 'laporan'])) {
            return ['intent' => AiIntent::GENERATE_REPORT, 'agent' => AiAgentType::REPORT];
        }

        // ── Knowledge Q&A ───────────────────────────────────────────────────
        if ($this->matches($q, ['apa itu', 'cari', 'bagaimana cara', 'sop', 'prosedur', 'panduan', 'jelaskan', 'apa yang dimaksud'])) {
            return ['intent' => AiIntent::KNOWLEDGE_QA, 'agent' => AiAgentType::KNOWLEDGE];
        }

        return ['intent' => AiIntent::GENERAL_CHAT, 'agent' => AiAgentType::GENERAL];
    }

    /** @param array<int, string> $keywords */
    private function matches(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($haystack, ' ' . $kw) || str_contains($haystack, $kw . ' ') || str_contains($haystack, $kw)) {
                return true;
            }
        }

        return false;
    }
}
