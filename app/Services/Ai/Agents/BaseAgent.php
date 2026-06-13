<?php
namespace App\Services\Ai\Agents;

use App\Models\User;

/**
 * Shared scaffolding: bakes the AXIOM-04 constraint into every system prompt
 * and provides sensible no-op defaults for context + proposed actions so
 * concrete agents only override what they specialise.
 */
abstract class BaseAgent implements AiAgent
{
    /** The role framing unique to this agent (without the AXIOM-04 suffix). */
    abstract protected function role(): string;

    public function systemPrompt(): string
    {
        // AXIOM-04 constraint baked into every system prompt.
        return $this->role() . ' Anda HANYA boleh MENGUSULKAN aksi (proposed_actions) '
            . 'atau menyusun DRAFT teks untuk ditinjau manusia; '
            . 'TIDAK PERNAH mengeksekusi, membuat, mengubah, atau menghapus data tanpa konfirmasi pengguna. '
            . 'Anda mewarisi izin pengguna yang sedang login dan tidak boleh melampauinya.';
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContext(User $user, array $ctx): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<int, array{type: string, payload: array<string, mixed>}>
     */
    public function proposeActions(User $user, string $content, array $ctx): array
    {
        return [];
    }
}
