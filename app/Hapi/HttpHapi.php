<?php

namespace App\Hapi;

use App\Exceptions\Hapi\HapiException;
use App\Exceptions\Hapi\HapiTimeout;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HttpHapi implements Hapi
{
    private const TOKEN_CACHE_KEY = 'regos:hapi:token';

    /**
     * @param  array{url: string, username: ?string, password: ?string, system: string, token_ttl: int, timeout: int, write_timeout: int}  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly Cache $cache,
        private readonly array $config,
    ) {}

    public function get(string $uri): array
    {
        return $this->decode($uri, $this->send('GET', $uri));
    }

    public function post(string $uri, array $payload): array
    {
        return $this->decode($uri, $this->send('POST', $uri, $payload));
    }

    public function raw(string $uri): string
    {
        return $this->send('GET', $uri)->body();
    }

    /**
     * Send an authenticated request, re-authenticating once if the cached
     * token has expired.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function send(string $method, string $uri, ?array $payload = null, bool $retried = false): Response
    {
        // Writes can be slow: creating a registration also sends the verification SMS and email.
        $timeout = $method === 'POST' ? $this->config['write_timeout'] : $this->config['timeout'];
        $response = $this->request($method, $uri, $payload, $this->token(), $timeout);

        if ($response->status() === 401 && ! $retried) {
            $this->cache->forget(self::TOKEN_CACHE_KEY);

            return $this->send($method, $uri, $payload, true);
        }

        if (! $response->successful()) {
            Log::warning('HAPI request failed', [
                'method' => $method,
                'uri' => $uri,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 2000),
            ]);

            $message = $response->json('message');

            throw new HapiException(
                is_string($message) && $message !== '' ? $message : 'HAPI request failed',
                $response->status(),
                is_array($response->json()) ? $response->json() : null,
            );
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function request(string $method, string $uri, ?array $payload, ?string $token, int $timeout): Response
    {
        $url = rtrim($this->config['url'], '/').$uri;

        $request = $this->http
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->when($token, fn (PendingRequest $request) => $request->withToken($token));

        try {
            return $method === 'POST'
                ? $request->asJson()->post($url, $payload ?? [])
                : $request->get($url);
        } catch (ConnectionException $exception) {
            Log::error('HAPI unreachable', ['uri' => $uri, 'error' => $exception->getMessage()]);

            if (str_starts_with($exception->getMessage(), 'cURL error 28:')) {
                throw new HapiTimeout('HAPI timed out', 0);
            }

            throw new HapiException('HAPI is unreachable', 0);
        }
    }

    private function token(): string
    {
        return $this->cache->remember(self::TOKEN_CACHE_KEY, $this->config['token_ttl'], function (): string {
            $response = $this->request('POST', '/auth', [
                'email' => $this->config['username'],
                'password' => $this->config['password'],
                'system' => $this->config['system'],
            ], null, $this->config['timeout']);

            $token = $response->json('token');

            if (! $response->successful() || ! is_string($token) || $token === '') {
                Log::error('HAPI authentication failed', ['status' => $response->status()]);

                throw new HapiException('HAPI authentication failed', $response->status());
            }

            return $token;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $uri, Response $response): array
    {
        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new HapiException('HAPI returned an invalid response for '.$uri, $response->status());
        }

        return $decoded;
    }
}
