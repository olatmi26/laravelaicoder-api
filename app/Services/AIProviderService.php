<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIProviderService
{
    // ── Main streaming entry point ──

    public function streamCompletion(
        User     $user,
        string   $systemPrompt,
        string   $userMessage,
        int      $maxTokens  = 4096,
        callable $onToken    = null,
        callable $onComplete = null,
        string   $provider   = null,
    ): void {
        // Resolve provider: BYOK > user preference > platform default
        $provider ??= $this->resolveProvider($user);
        $apiKey     = $this->resolveApiKey($user, $provider);
        $model      = $this->resolveModel($user, $provider);

        match ($provider) {
            'anthropic' => $this->streamAnthropic($apiKey, $model, $systemPrompt, $userMessage, $maxTokens, $onToken, $onComplete),
            'openai'    => $this->streamOpenAI($apiKey, $model, $systemPrompt, $userMessage, $maxTokens, $onToken, $onComplete),
            default     => throw new \InvalidArgumentException("Unsupported AI provider: {$provider}"),
        };
    }

    // ── Anthropic Claude Streaming ──

    private function streamAnthropic(
        string $apiKey, string $model,
        string $systemPrompt, string $userMessage,
        int $maxTokens, ?callable $onToken, ?callable $onComplete,
    ): void {
        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->withOptions(['stream' => true])
          ->post('https://api.anthropic.com/v1/messages', [
              'model'      => $model,
              'max_tokens' => $maxTokens,
              'system'     => $systemPrompt,
              'messages'   => [['role' => 'user', 'content' => $userMessage]],
              'stream'     => true,
          ]);

        $inputTokens  = 0;
        $outputTokens = 0;
        $buffer       = '';

        $response->thenReturn(); // force stream

        foreach (explode("\n", $response->body()) as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) continue;

            $json = json_decode(substr($line, 6), true);
            if (!$json) continue;

            $type = $json['type'] ?? '';

            if ($type === 'content_block_delta') {
                $token = $json['delta']['text'] ?? '';
                if ($token && $onToken) $onToken($token);
            }

            if ($type === 'message_start') {
                $inputTokens = $json['message']['usage']['input_tokens'] ?? 0;
            }

            if ($type === 'message_delta') {
                $outputTokens = $json['usage']['output_tokens'] ?? 0;
            }

            if ($type === 'message_stop') {
                if ($onComplete) $onComplete([
                    'input_tokens'  => $inputTokens,
                    'output_tokens' => $outputTokens,
                ]);
            }
        }
    }

    // ── OpenAI GPT Streaming ──

    private function streamOpenAI(
        string $apiKey, string $model,
        string $systemPrompt, string $userMessage,
        int $maxTokens, ?callable $onToken, ?callable $onComplete,
    ): void {
        $response = Http::withToken($apiKey)
            ->withOptions(['stream' => true])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'      => $model,
                'max_tokens' => $maxTokens,
                'stream'     => true,
                'messages'   => [
                    ['role' => 'system',  'content' => $systemPrompt],
                    ['role' => 'user',    'content' => $userMessage],
                ],
            ]);

        $promptTokens     = 0;
        $completionTokens = 0;

        foreach (explode("\n", $response->body()) as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) continue;
            if ($line === 'data: [DONE]') break;

            $json  = json_decode(substr($line, 6), true);
            $delta = $json['choices'][0]['delta']['content'] ?? '';

            if ($delta && $onToken) $onToken($delta);

            if (isset($json['usage'])) {
                $promptTokens     = $json['usage']['prompt_tokens'] ?? 0;
                $completionTokens = $json['usage']['completion_tokens'] ?? 0;
            }
        }

        if ($onComplete) $onComplete([
            'input_tokens'  => $promptTokens,
            'output_tokens' => $completionTokens,
        ]);
    }

    // ── Key Resolution ──

    private function resolveProvider(User $user): string
    {
        // Check if user has any active BYOK key
        foreach (['anthropic', 'openai'] as $provider) {
            if ($user->getApiKeyForProvider($provider)) return $provider;
        }
        return config('agents.default_provider', 'anthropic');
    }

    private function resolveApiKey(User $user, string $provider): string
    {
        $byok = $user->getApiKeyForProvider($provider);
        if ($byok) {
            $byok->markUsed();
            return $byok->getDecryptedKey();
        }

        $platformKey = match($provider) {
            'anthropic' => config('services.anthropic.key'),
            'openai'    => config('services.openai.key'),
            default     => throw new \RuntimeException("No API key configured for {$provider}"),
        };

        if (!$platformKey) {
            throw new \RuntimeException("No API key available for provider: {$provider}. Please add your API key in Settings.");
        }

        return $platformKey;
    }

    private function resolveModel(User $user, string $provider): string
    {
        $byok = $user->getApiKeyForProvider($provider);
        if ($byok) return $byok->model;
        return config("agents.models.{$provider}", 'claude-sonnet-4-5');
    }
}
