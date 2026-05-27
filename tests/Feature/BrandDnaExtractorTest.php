<?php

use App\Models\Tenant;
use App\Services\Agents\AnthropicClient;
use App\Services\Brand\BrandDnaExtractor;
use App\Support\TenantContext;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

it('extracts a brand profile from HTML and writes it to the tenant', function () {
    // Mock the website fetch
    $siteMock = new MockHandler([
        new Response(200, [], '<html><body><h1>Beta — calm fintech for teams</h1><p>We help product teams ship financial workflows.</p></body></html>'),
    ]);
    $siteClient = new Client(['handler' => HandlerStack::create($siteMock)]);

    // Mock the Anthropic call
    $anthropicMock = new MockHandler([
        new Response(200, [], json_encode([
            'content' => [[
                'type'  => 'tool_use',
                'name'  => 'submit_brand_profile',
                'input' => [
                    'name'        => 'Beta',
                    'tagline'     => 'Calm fintech for teams',
                    'voice_short' => 'calm, technical, no hype',
                    'tone'        => ['calm', 'precise'],
                    'audience'    => ['product teams', 'fintech'],
                    'pillars'     => ['reliability', 'workflow speed', 'audit trail'],
                ],
            ]],
            'usage' => ['input_tokens' => 200, 'output_tokens' => 80],
        ])),
    ]);
    $anthropicClient = new Client(['base_uri' => 'https://api.anthropic.com', 'handler' => HandlerStack::create($anthropicMock)]);
    $anthropic = new AnthropicClient($anthropicClient);

    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'Beta', 'slug' => 'beta', 'contact_email' => 'o@beta.test']);
    TenantContext::bind($tenant);

    $extractor = new BrandDnaExtractor($anthropic, $siteClient);
    $profile = $extractor->extract($tenant, 'https://beta.example');

    $tenant->refresh();
    expect($profile['voice_short'])->toBe('calm, technical, no hype')
        ->and($tenant->brand_voice)->toBe('calm, technical, no hype')
        ->and($tenant->brand_profile['source_url'])->toBe('https://beta.example')
        ->and($tenant->brand_profile['pillars'])->toContain('reliability');
});
