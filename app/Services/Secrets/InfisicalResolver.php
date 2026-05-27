<?php

namespace App\Services\Secrets;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InfisicalResolver
{
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    /** In-memory memo for the lifetime of the process. Persistent caching
     *  via Laravel's Cache facade is not safe here — the resolver runs
     *  inside SecretsServiceProvider::register(), which fires before the
     *  cache binding is registered (and also runs during `config:cache`
     *  build-time where there is no cache store at all). Each PHP worker
     *  resolves once on first request and reuses for the rest of its life.
     *  @var array<string,string> */
    private array $memo = [];

    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly ?Client $http = null,
    ) {}

    public function resolve(string $handle): string
    {
        if (! str_starts_with($handle, 'secret://')) {
            return $handle;
        }

        if (isset($this->memo[$handle])) {
            return $this->memo[$handle];
        }

        $parsed = self::parseHandle($handle);
        if ($parsed === null) {
            Log::warning('InfisicalResolver: malformed handle', ['handle' => $handle]);
            return $handle;
        }

        try {
            $value = $this->fetch($parsed['environment'], $parsed['path'], $parsed['name']);
            $this->memo[$handle] = $value;
            return $value;
        } catch (\Throwable $e) {
            // Log only structural metadata — never the exception message, which
            // may include partial response bodies or transient values.
            Log::error('InfisicalResolver: fetch failed', [
                'project'     => $parsed['project'],
                'environment' => $parsed['environment'],
                'path'        => $parsed['path'],
                'name'        => $parsed['name'],
                'error_class' => $e::class,
            ]);
            throw $e;
        }
    }

    /** @return array{project:string,environment:string,path:string,name:string}|null */
    public static function parseHandle(string $handle): ?array
    {
        if (! preg_match('#^secret://([^/]+)/([^/]+)(/.*)?/([A-Z0-9_]+)$#', $handle, $m)) {
            return null;
        }
        return [
            'project'     => $m[1],
            'environment' => $m[2],
            'path'        => ($m[3] ?? '') === '' ? '/' : $m[3],
            'name'        => $m[4],
        ];
    }

    private function ensureToken(): void
    {
        if ($this->accessToken !== null && time() < $this->accessTokenExpiresAt - 30) {
            return;
        }

        $clientId     = $this->config['client_id']     ?? null;
        $clientSecret = $this->config['client_secret'] ?? null;
        if (empty($clientId) || empty($clientSecret)) {
            throw new RuntimeException('Infisical credentials are not configured.');
        }

        $response = $this->client()->post('/api/v1/auth/universal-auth/login', [
            'json' => [
                'clientId'     => $clientId,
                'clientSecret' => $clientSecret,
            ],
            'timeout' => (int) ($this->config['request_timeout'] ?? 5),
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if (! is_array($body) || ! isset($body['accessToken'])) {
            throw new RuntimeException('Infisical universal-auth did not return an access token.');
        }

        $this->accessToken = (string) $body['accessToken'];
        $this->accessTokenExpiresAt = time() + (int) ($body['expiresIn'] ?? 3600);
    }

    private function fetch(string $environment, string $path, string $name): string
    {
        $this->ensureToken();

        $projectId = $this->config['project_id'] ?? null;
        if (empty($projectId)) {
            throw new RuntimeException('INFISICAL_PROJECT_ID is not configured.');
        }

        $response = $this->client()->get('/api/v3/secrets/raw/'.rawurlencode($name), [
            'query' => [
                'workspaceId' => $projectId,
                'environment' => $environment,
                'secretPath'  => $path,
            ],
            'headers' => [
                'Authorization' => 'Bearer '.$this->accessToken,
            ],
            'timeout' => (int) ($this->config['request_timeout'] ?? 5),
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $value = $body['secret']['secretValue'] ?? null;
        if (! is_string($value)) {
            throw new RuntimeException("Infisical returned no value for {$name} in {$environment}.");
        }
        return $value;
    }

    private function client(): Client
    {
        return $this->http ?? new Client([
            'base_uri'    => rtrim((string) ($this->config['site_url'] ?? 'https://app.infisical.com'), '/'),
            'http_errors' => true,
        ]);
    }

    public function healthCheck(): bool
    {
        $this->ensureToken();
        return $this->accessToken !== null;
    }
}
