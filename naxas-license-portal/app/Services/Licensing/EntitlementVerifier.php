<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;
use RuntimeException;

class EntitlementVerifier
{
    public function __construct(private DomainNormalizer $domains) {}

    public function verify(string $token, string $installationUuid, string $domain): array
    {
        if (strlen($token) > config('licensing.max_response_bytes') || substr_count($token, '.') !== 1) {
            throw new RuntimeException('The signed license has an invalid envelope.');
        }

        [$encodedPayload, $encodedSignature] = explode('.', $token, 2);
        $payloadBytes = $this->decode($encodedPayload);
        $signature = $this->decode($encodedSignature);
        $keyPath = config('licensing.public_key_path');
        if (! $keyPath || ! is_file($keyPath) || ! is_readable($keyPath)) {
            throw new RuntimeException('The license verification key is unavailable.');
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($keyPath));
        if (! $publicKey || openssl_verify($payloadBytes, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('The signed license could not be verified.');
        }

        $payload = json_decode($payloadBytes, true, 32, JSON_THROW_ON_ERROR);
        $valid = ($payload['product'] ?? null) === config('licensing.product')
            && ($payload['type'] ?? null) === config('licensing.license_type')
            && hash_equals(strtolower($installationUuid), strtolower((string) ($payload['installation_uuid'] ?? '')))
            && $this->domainMatches($payload, $domain)
            && (! ($payload['expires_at'] ?? null) || CarbonImmutable::parse($payload['expires_at'])->isFuture());

        throw_unless($valid, RuntimeException::class, 'The signed license does not match this installation.');

        return $payload;
    }

    private function domainMatches(array $payload, string $domain): bool
    {
        $host = $this->domains->normalize($domain);
        if ($payload['production_domain'] ?? null) {
            return hash_equals($payload['production_domain'], $host);
        }

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test') || str_ends_with($host, '.local');
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        throw_if($decoded === false, RuntimeException::class, 'The signed license has invalid encoding.');

        return $decoded;
    }
}
