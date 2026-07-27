<?php

namespace App\Services\Licensing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PortalClient
{
    public function create(array $payload): array
    {
        return $this->post('/api/v1/activation-requests', $payload, 201);
    }

    public function status(string $requestId, array $proof): array
    {
        return $this->post('/api/v1/activation-requests/'.$requestId.'/status', $proof);
    }

    public function acknowledge(string $requestId, array $proof): array
    {
        return $this->post('/api/v1/activation-requests/'.$requestId.'/acknowledge', $proof);
    }

    private function post(string $path, array $payload, int $expected = 200): array
    {
        $base = rtrim((string) config('licensing.portal_url'), '/');
        $this->assertAllowedUrl($base);

        try {
            $response = Http::acceptJson()->asJson()->timeout(config('licensing.timeout'))->post($base.$path, $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('The license portal is currently unavailable.');
        }

        if (strlen($response->body()) > config('licensing.max_response_bytes')) {
            throw new RuntimeException('The license portal returned an oversized response.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('The license portal returned an invalid response.');
        }

        if ($response->status() !== $expected) {
            throw new RuntimeException((string) ($body['safe_message'] ?? $body['message'] ?? 'The license portal rejected the request.'));
        }

        return $body;
    }

    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? '';
        $host = strtolower($parts['host'] ?? '');
        if ($scheme === 'https') {
            return;
        }

        $allowed = app()->environment(['local', 'testing'])
            && config('licensing.allow_local_http')
            && $scheme === 'http'
            && in_array($host, config('licensing.trusted_local_hosts'), true);

        throw_unless($allowed, RuntimeException::class, 'The license portal URL must use HTTPS.');
    }
}
