<?php
namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Anthropic Claude provider via the Messages API. Only used when an api_key is
 * present (gated by HttpAiProvider::isAvailable); unreachable in tests.
 */
class ClaudeProvider extends HttpAiProvider
{
    public function name(): string
    {
        return 'claude';
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AiResult
    {
        $apiKey = $this->apiKey();
        $model  = $this->model();

        // Claude takes a top-level "system" string + user/assistant turns.
        $system = [];
        $turns  = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system[] = (string) $m['content'];
                continue;
            }
            $turns[] = [
                'role'    => $m['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $m['content'],
            ];
        }

        $payload = [
            'model'      => $model,
            'max_tokens' => (int) ($this->config['max_tokens'] ?? 2048),
            'messages'   => $turns,
        ];
        if ($system !== []) {
            $payload['system'] = implode("\n\n", $system);
        }
        if (isset($this->config['temperature'])) {
            $payload['temperature'] = (float) $this->config['temperature'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->asJson()
                ->post($this->baseUri() . '/v1/messages', $payload);

            if ($response->failed()) {
                throw new AiProviderException(
                    'claude: HTTP ' . $response->status() . ' - ' . $this->safeBody($response->body())
                );
            }

            $body = $response->json();
        } catch (AiProviderException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new AiProviderException('claude: ' . self::redact($e->getMessage()), 0, $e);
        }

        $text  = $body['content'][0]['text'] ?? '';
        $usage = $body['usage'] ?? [];

        return new AiResult(
            content: (string) $text,
            promptTokens: isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null,
            completionTokens: isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null,
            modelName: $model,
            finishReason: isset($body['stop_reason']) ? (string) $body['stop_reason'] : null,
            proposedActions: [],
            citations: [],
        );
    }
}
