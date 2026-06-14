<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Ai\Providers\HttpAiProvider;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Regression coverage for H1/H2: a provider failure must NEVER let a secret
 * (api key / bearer token / auth header) reach the application log when the
 * AiProviderManager records the fallback warning.
 */
class AiProviderSecurityTest extends TestCase
{
    public function test_fallback_warning_does_not_log_secrets(): void
    {
        $spy = Log::spy();

        // A provider that is "available" but throws an exception whose message
        // embeds several fake secrets (as a misbehaving real provider might).
        $leaky = new class implements AiProvider {
            public function name(): string
            {
                return 'leaky';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $options = []): \App\Services\Ai\AiResult
            {
                throw new AiProviderException(
                    'leaky: HTTP 401 - https://api.example.com/v1/x?key=SECRET123 '
                    . 'Authorization: Bearer SECRET123 x-goog-api-key: SECRET123 '
                    . 'api_key=SECRET123'
                );
            }
        };

        $manager = new AiProviderManager([$leaky, new FakeAiProvider()], ['leaky', 'fake']);

        $outcome = $manager->chatWithFallback([['role' => 'user', 'content' => 'halo']], []);

        // It still falls through to fake (behaviour unchanged).
        $this->assertSame('fake', $outcome['provider']);
        $this->assertTrue($outcome['fellBack']);

        // The captured warning message + context must NOT contain the secret.
        /** @var MockInterface $spy */
        $spy->shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []): bool {
                $haystack = $message . ' ' . json_encode($context);
                $this->assertStringNotContainsString('SECRET123', $haystack);

                return true;
            });
    }

    public function test_redact_strips_all_credential_patterns(): void
    {
        $raw = 'url?key=SECRET123 api_key=SECRET123 '
            . 'Authorization: Bearer SECRET123 '
            . 'Bearer SECRET123 '
            . 'x-goog-api-key: SECRET123 '
            . 'x-api-key: SECRET123';

        $redacted = HttpAiProvider::redact($raw);

        $this->assertStringNotContainsString('SECRET123', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
