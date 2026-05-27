<?php

use App\Services\Secrets\InfisicalResolver;

it('parses a valid handle', function () {
    $h = 'secret://eiaaw-recruiter-prod/prod/auth/ANTHROPIC_API_KEY';
    $parsed = InfisicalResolver::parseHandle($h);
    expect($parsed)->not->toBeNull()
        ->and($parsed['project'])->toBe('eiaaw-recruiter-prod')
        ->and($parsed['environment'])->toBe('prod')
        ->and($parsed['path'])->toBe('/auth')
        ->and($parsed['name'])->toBe('ANTHROPIC_API_KEY');
});

it('parses a handle with root path', function () {
    $parsed = InfisicalResolver::parseHandle('secret://demo/prod/DB_PASSWORD');
    expect($parsed)->not->toBeNull()
        ->and($parsed['path'])->toBe('/')
        ->and($parsed['name'])->toBe('DB_PASSWORD');
});

it('rejects malformed handles', function () {
    expect(InfisicalResolver::parseHandle('not-a-handle'))->toBeNull();
    expect(InfisicalResolver::parseHandle('secret://only-one-part'))->toBeNull();
});

it('passes through non-handle strings unchanged', function () {
    $resolver = new InfisicalResolver(['enabled' => false]);
    expect($resolver->resolve('plain-value'))->toBe('plain-value');
});
