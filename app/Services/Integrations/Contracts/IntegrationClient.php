<?php
namespace App\Services\Integrations\Contracts;

use App\Enums\IntegrationProvider;
use App\Models\Organization;
use App\Services\Integrations\IntegrationSyncResult;

/**
 * Production-swappable client for one external system. Mirrors the AI provider
 * pattern: every client ships a deterministic STUB used in dev/test and a real
 * path gated behind isConfigured() AND config('integrations.live'). A client
 * MUST NOT make a network call when live is false.
 */
interface IntegrationClient
{
    public function provider(): IntegrationProvider;

    /** True when the org has the minimum required credentials configured. */
    public function isConfigured(Organization $org): bool;

    /**
     * Lightweight reachability/credential check.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(Organization $org): array;

    /**
     * Perform the provider's primary sync. Deterministic in stub mode.
     *
     * @param array<string, mixed> $options
     */
    public function sync(Organization $org, array $options = []): IntegrationSyncResult;
}
