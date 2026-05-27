<?php

namespace App\Services\Agents;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin HTTP client for Anthropic Messages API.
 *
 * Prompt caching ENABLED on system prompts and tool definitions per the
 * EIAAW global Claude API guidance. We hold a single client per-request,
 * not a singleton with state.
 */
class AnthropicClient
{
    private Client $http;
    private string $apiKey;
    private string $baseUrl;
    private int    $timeout;

    public function __construct(?Client $http = null)
    {
        $this->apiKey  = (string) config('services.anthropic.api_key', '');
        $this->baseUrl = rtrim((string) config('services.anthropic.base_url', 'https://api.anthropic.com'), '/');
        $this->timeout = (int) config('services.anthropic.timeout', 120);

        $this->http = $http ?? new Client([
            'base_uri'    => $this->baseUrl,
            'http_errors' => true,
            'timeout'     => $this->timeout,
        ]);
    }

    /**
     * Send a Messages request. Returns the decoded API response.
     *
     * @param  array<int, array{role:string,content:mixed}> $messages
     * @param  array<int, array<string,mixed>> $tools
     * @return array<string, mixed>
     */
    public function messages(
        string $model,
        array $messages,
        string|array $system = '',
        array $tools = [],
        int $maxTokens = 4096,
        ?float $temperature = null,
        bool $cacheSystem = true,
    ): array {
        if ($this->apiKey === '' || str_starts_with($this->apiKey, 'secret://')) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not resolved. Check Infisical wiring.');
        }

        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $messages,
        ];

        if ($system !== '' && $system !== []) {
            $body['system'] = is_array($system)
                ? $system
                : [
                    [
                        'type' => 'text',
                        'text' => $system,
                        'cache_control' => $cacheSystem ? ['type' => 'ephemeral'] : null,
                    ],
                ];
            // Strip null cache_control entries
            foreach ($body['system'] as $i => $block) {
                if (($block['cache_control'] ?? null) === null) {
                    unset($body['system'][$i]['cache_control']);
                }
            }
        }

        if (! empty($tools)) {
            // Cache the last tool block to leverage prompt caching on tool defs.
            $tools[count($tools) - 1]['cache_control'] = ['type' => 'ephemeral'];
            $body['tools'] = $tools;
        }

        if ($temperature !== null) {
            $body['temperature'] = $temperature;
        }

        try {
            $resp = $this->http->post('/v1/messages', [
                'headers' => [
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json'    => $body,
                'timeout' => $this->timeout,
            ]);
            return json_decode((string) $resp->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            // Don't log Guzzle's full error message — it can include request
            // headers (incl. the `x-api-key` we just sent) or response bodies.
            Log::error('AnthropicClient: request failed', [
                'model'       => $model,
                'error_class' => $e::class,
                'code'        => $e->getCode(),
            ]);
            throw new RuntimeException('Anthropic request failed.', 0, $e);
        }
    }

    /**
     * Extract concatenated text from a Messages API response.
     */
    public static function extractText(array $response): string
    {
        $out = '';
        foreach (($response['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $out .= $block['text'] ?? '';
            }
        }
        return $out;
    }

    /**
     * Pull out a tool_use block by name (returns its `input` array or null).
     */
    public static function extractToolUse(array $response, string $toolName): ?array
    {
        foreach (($response['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? '') === $toolName) {
                return $block['input'] ?? [];
            }
        }
        return null;
    }

    /**
     * Decode the first JSON object in a text response. Returns null on failure.
     */
    public static function extractJson(string $text): ?array
    {
        // Strip code fences if present
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode((string) $text, true);
        if (is_array($decoded)) return $decoded;

        // Fall back: find the first {...} block
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
        }
        return null;
    }
}
