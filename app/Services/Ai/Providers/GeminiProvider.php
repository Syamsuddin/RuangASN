<?php
namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Gemini provider via the generativeLanguage REST API. Only used when
 * GEMINI_API_KEY is present; unreachable in tests. The request shape matches
 * the v1beta generateContent endpoint.
 */
class GeminiProvider extends HttpAiProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AiResult
    {
        $apiKey = $this->apiKey();
        $model  = $this->model();

        // Gemini uses "user"/"model" roles; system prompts go in systemInstruction.
        $systemParts = [];
        $contents    = [];
        foreach ($messages as $m) {
            $role = $m['role'];
            $text = (string) $m['content'];
            if ($role === 'system') {
                $systemParts[] = ['text' => $text];
                continue;
            }
            $contents[] = [
                'role'  => $role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $text]],
            ];
        }

        $payload = ['contents' => $contents];
        if ($systemParts !== []) {
            $payload['systemInstruction'] = ['parts' => $systemParts];
        }

        try {
            $response = Http::timeout(30)
                ->asJson()
                ->post(
                    $this->baseUri() . "/v1beta/models/{$model}:generateContent?key={$apiKey}",
                    $payload
                );

            if ($response->failed()) {
                throw new AiProviderException(
                    "gemini: HTTP {$response->status()} - " . $response->body()
                );
            }

            $body = $response->json();
        } catch (AiProviderException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new AiProviderException('gemini: ' . $e->getMessage(), 0, $e);
        }

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $finish = $body['candidates'][0]['finishReason'] ?? null;
        $usage  = $body['usageMetadata'] ?? [];

        return new AiResult(
            content: (string) $text,
            promptTokens: isset($usage['promptTokenCount']) ? (int) $usage['promptTokenCount'] : null,
            completionTokens: isset($usage['candidatesTokenCount']) ? (int) $usage['candidatesTokenCount'] : null,
            modelName: $model,
            finishReason: $finish !== null ? (string) $finish : null,
            proposedActions: [],
            citations: [],
        );
    }
}
