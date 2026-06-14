<?php
namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenAI provider via the Chat Completions API. Only used when an api_key is
 * present (gated by HttpAiProvider::isAvailable); unreachable in tests.
 */
class OpenAiProvider extends HttpAiProvider
{
    public function name(): string
    {
        return 'openai';
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AiResult
    {
        $apiKey = $this->apiKey();
        $model  = $this->model();

        $turns = [];
        foreach ($messages as $m) {
            $turns[] = [
                'role'    => in_array($m['role'], ['system', 'assistant', 'user'], true) ? $m['role'] : 'user',
                'content' => (string) $m['content'],
            ];
        }

        $payload = [
            'model'    => $model,
            'messages' => $turns,
        ];
        if (isset($this->config['max_tokens'])) {
            $payload['max_tokens'] = (int) $this->config['max_tokens'];
        }
        if (isset($this->config['temperature'])) {
            $payload['temperature'] = (float) $this->config['temperature'];
        }

        try {
            $response = Http::timeout(30)
                ->withToken($apiKey)
                ->asJson()
                ->post($this->baseUri() . '/v1/chat/completions', $payload);

            if ($response->failed()) {
                throw new AiProviderException("openai: HTTP {$response->status()} - " . $response->body());
            }

            $body = $response->json();
        } catch (AiProviderException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new AiProviderException('openai: ' . $e->getMessage(), 0, $e);
        }

        $text   = $body['choices'][0]['message']['content'] ?? '';
        $finish = $body['choices'][0]['finish_reason'] ?? null;
        $usage  = $body['usage'] ?? [];

        return new AiResult(
            content: (string) $text,
            promptTokens: isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            completionTokens: isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
            modelName: $model,
            finishReason: $finish !== null ? (string) $finish : null,
            proposedActions: [],
            citations: [],
        );
    }
}
