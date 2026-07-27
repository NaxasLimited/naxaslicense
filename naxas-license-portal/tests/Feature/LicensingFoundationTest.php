<?php

namespace Tests\Feature;

use App\Models\LicenseState;
use App\Services\Licensing\EntitlementVerifier;
use App\Services\Licensing\PortalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LicensingFoundationTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privateKey = sys_get_temp_dir().'/buildora-private-'.getmypid().'.pem';
        $this->publicKey = sys_get_temp_dir().'/buildora-public-'.getmypid().'.pem';
        exec('openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out '.escapeshellarg($this->privateKey).' 2>/dev/null');
        $key = openssl_pkey_get_private(file_get_contents($this->privateKey));
        file_put_contents($this->publicKey, openssl_pkey_get_details($key)['key']);
        config(['licensing.public_key_path' => $this->publicKey]);
    }

    protected function tearDown(): void
    {
        @unlink($this->privateKey);
        @unlink($this->publicKey);
        parent::tearDown();
    }

    public function test_verifier_accepts_bound_entitlement_and_rejects_tampering(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $token = $this->sign(['product' => 'buildora-cms', 'type' => 'single_site',
            'installation_uuid' => $uuid, 'production_domain' => 'example.com',
            'expires_at' => now()->addDay()->toIso8601String()]);
        $payload = app(EntitlementVerifier::class)->verify($token, $uuid, 'https://www.example.com/path');
        $this->assertSame('buildora-cms', $payload['product']);
        $this->expectException(RuntimeException::class);
        app(EntitlementVerifier::class)->verify($token.'x', $uuid, 'example.com');
    }

    public function test_client_requires_explicit_local_http_opt_in(): void
    {
        config(['licensing.portal_url' => 'http://127.0.0.1:8001', 'licensing.allow_local_http' => false]);
        $this->expectException(RuntimeException::class);
        app(PortalClient::class)->status('request', []);
    }

    public function test_invalid_approved_response_does_not_replace_existing_activation(): void
    {
        $state = LicenseState::create(['installation_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'request_id' => '660e8400-e29b-41d4-a716-446655440000', 'request_token' => 'BRQ-AAAA-BBBB-CCCC',
            'request_status' => 'active', 'signed_license' => 'known-valid', 'entitlement' => ['product' => 'buildora-cms']]);
        Http::fake(['*' => Http::response(['status' => 'approved', 'signed_license' => 'tampered.invalid'], 200)]);
        config(['licensing.portal_url' => 'http://127.0.0.1:8001', 'licensing.allow_local_http' => true]);
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)->post(route('license.poll'));
        $this->assertSame('known-valid', $state->fresh()->signed_license);
        $this->assertSame('active', $state->request_status);
    }

    private function sign(array $payload): string
    {
        $bytes = json_encode($payload, JSON_UNESCAPED_SLASHES);
        openssl_sign($bytes, $signature, file_get_contents($this->privateKey), OPENSSL_ALGO_SHA256);
        $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        return $encode($bytes).'.'.$encode($signature);
    }
}
