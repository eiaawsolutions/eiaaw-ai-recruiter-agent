<?php

namespace App\Services\Brand;

use App\Models\Tenant;
use App\Services\Agents\AnthropicClient;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fetches a tenant's website, strips the markup, asks Claude for a structured
 * brand profile (tone, palette, audience, pillars), and writes it back to
 * `tenants.brand_profile` + `tenants.brand_voice`.
 *
 * DraftingAgent reads `brand_voice` in its system prompt, so this directly
 * grounds outreach in the tenant's actual brand. Per /full-stack-engineer
 * Phase 1.6 + claudekit `ckm-brand` discipline.
 */
class BrandDnaExtractor
{
    private const MAX_BYTES = 800_000; // ~800KB of HTML is plenty for a homepage

    public function __construct(
        private readonly AnthropicClient $anthropic,
        private ?Client $http = null,
    ) {}

    public function extract(Tenant $tenant, string $url): array
    {
        $html = $this->fetch($url);
        $text = $this->stripHtml($html);
        $excerpt = mb_substr($text, 0, 12_000);

        $model = (string) config('services.anthropic.draft_model', 'claude-haiku-4-5-20251001');
        $response = $this->anthropic->messages(
            model: $model,
            messages: [['role' => 'user', 'content' => $this->userPrompt($url, $excerpt)]],
            system: $this->systemPrompt(),
            tools: [$this->tool()],
            maxTokens: 1500,
            temperature: 0.1,
        );

        $profile = AnthropicClient::extractToolUse($response, 'submit_brand_profile') ?? [];
        $profile['source_url']   = $url;
        $profile['extracted_at'] = now()->toIso8601String();

        $tenant->forceFill([
            'brand_profile' => $profile,
            'brand_voice'   => (string) ($profile['voice_short'] ?? $tenant->brand_voice),
        ])->saveQuietly();

        return $profile;
    }

    private function fetch(string $url): string
    {
        try {
            $http = $this->http ?? new Client([
                'http_errors' => true,
                'timeout'     => 25,
                'headers'     => ['User-Agent' => 'EIAAW-Recruiter-BrandExtractor/1.0'],
            ]);
            $resp = $http->get($url, ['allow_redirects' => true]);
            $body = (string) $resp->getBody();
            return mb_substr($body, 0, self::MAX_BYTES);
        } catch (\Throwable $e) {
            Log::warning('BrandDnaExtractor: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            throw new RuntimeException("Could not fetch brand source URL: {$e->getMessage()}", 0, $e);
        }
    }

    private function stripHtml(string $html): string
    {
        // Remove scripts/styles before strip_tags so they don't leak text.
        $html = preg_replace('#<(script|style|noscript|svg)[^>]*>.*?</\1>#is', ' ', (string) $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim((string) $text);
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are EIAAW Recruiter's Brand DNA extractor. Read the body text of a
company website and return a structured brand profile that downstream
outreach drafting can ground itself in.

Rules:
- Voice & tone come from how they actually write, not from boilerplate.
- Audience: who they serve (industry, persona, geo). Be specific.
- Pillars: 3-5 actual differentiators backed by phrases in the text.
- Forbidden topics: anything to avoid in outreach (industries, language).
- Tone examples: 2-3 short example sentences in their voice.
- Never invent numbers or accolades the text does not state.

Return via the `submit_brand_profile` tool only.
PROMPT;
    }

    private function userPrompt(string $url, string $excerpt): string
    {
        return <<<USER
Source URL: {$url}

Body text (truncated):
---
{$excerpt}
---

Extract the brand profile via the tool.
USER;
    }

    private function tool(): array
    {
        return [
            'name'        => 'submit_brand_profile',
            'description' => 'Submit a structured brand DNA profile.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'name'             => ['type' => 'string'],
                    'tagline'          => ['type' => 'string'],
                    'voice_short'      => ['type' => 'string', 'description' => '5-10 words; goes into outreach system prompts.'],
                    'tone'             => ['type' => 'array', 'items' => ['type' => 'string']],
                    'audience'         => ['type' => 'array', 'items' => ['type' => 'string']],
                    'pillars'          => ['type' => 'array', 'items' => ['type' => 'string']],
                    'tone_examples'    => ['type' => 'array', 'items' => ['type' => 'string']],
                    'forbidden_topics' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'palette_hints'    => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['voice_short', 'tone', 'audience', 'pillars'],
            ],
        ];
    }
}
