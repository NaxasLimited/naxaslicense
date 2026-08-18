<?php

namespace Tests\Feature;

use App\Models\ActivationRequest;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildoraActivationContractTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->key = sys_get_temp_dir().'/naxas-test-'.getmypid().'.pem';
        exec('openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out '.escapeshellarg($this->key).' 2>/dev/null');
        $p = openssl_pkey_get_private(file_get_contents($this->key));
        $this->public = openssl_pkey_get_details($p)['key'];
        config(['license.private_key_path' => $this->key]);
    }

    protected function tearDown(): void
    {
        @unlink($this->key);
        parent::tearDown();
    }

    public function test_end_to_end_buildora_contract_and_replay_protection(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $create = $this->postJson('/api/v1/activation-requests', ['product' => 'buildora-cms', 'version' => '1.2.3', 'license_type' => 'single_site', 'installation_uuid' => $uuid, 'domain' => 'https://WWW.Example.com/path', 'environment' => 'production', 'nonce' => str_repeat('a', 32)])->assertCreated()->assertJsonStructure(['request_id', 'request_token', 'status', 'expires_at', 'portal_url']);
        $proof = $create->json();
        $a = ActivationRequest::first();
        $this->assertNotEquals($proof['request_token'], $a->request_token_hash);
        $this->assertEquals('www.example.com', $a->normalized_domain);
        $e = Product::first()->editions()->first();
        $c = Customer::create(['name' => 'Buyer', 'email' => 'buyer@example.com', 'status' => 'active']);
        $l = License::create(['license_id' => 'NAXAS-BLD-ABCDEFGH', 'customer_id' => $c->id, 'product_id' => $e->product_id, 'product_edition_id' => $e->id, 'status' => 'active', 'license_type' => 'single_site', 'production_domain_limit' => 1, 'update_entitlement' => true, 'support_entitlement' => false]);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->assertNull($l->issued_at);
        $this->actingAs($admin)->post('/admin/activation-requests/'.$a->id.'/approve', ['license_id' => $l->id])->assertRedirect();
        $this->assertNotNull($l->fresh()->issued_at);
        $status = $this->postJson('/api/v1/activation-requests/'.$proof['request_id'].'/status', ['request_token' => $proof['request_token'], 'installation_uuid' => $uuid])->assertOk()->assertJsonPath('status', 'approved');
        $token = $status->json('signed_license');
        [$encoded,$signature] = explode('.', $token);
        $payloadBytes = $this->decode($encoded);
        $this->assertSame(1, openssl_verify($payloadBytes, $this->decode($signature), $this->public, OPENSSL_ALGO_SHA256));
        $payload = json_decode($payloadBytes, true);
        $this->assertSame(config('license.key_id'), $payload['key_id']);
        $this->assertSame('buildora-cms', $payload['product']);
        $this->assertSame('single_site', $payload['type']);
        $this->assertSame('single_site', $payload['license_type']);
        $this->assertSame('active', $payload['status']);
        $this->assertSame($uuid, $payload['installation_uuid']);
        $this->assertSame('www.example.com', $payload['domain']);
        $this->assertSame('example.com', $payload['production_domain']);
        $this->assertTrue($payload['update_entitlement']);
        $this->assertFalse($payload['support_entitlement']);
        $this->assertNotSame(1, openssl_verify($payloadBytes.'x', $this->decode($signature), $this->public, OPENSSL_ALGO_SHA256));
        $retry = $this->postJson('/api/v1/activation-requests/'.$proof['request_id'].'/status', ['request_token' => $proof['request_token'], 'installation_uuid' => $uuid])->assertJsonPath('status', 'approved');
        $this->assertSame($token, $retry->json('signed_license'));
        $fingerprint = hash('sha256', $token);
        $this->postJson('/api/v1/activation-requests/'.$proof['request_id'].'/acknowledge', ['request_token' => $proof['request_token'], 'installation_uuid' => $uuid, 'entitlement_fingerprint' => $fingerprint])->assertJson(['status' => 'completed']);
        $this->postJson('/api/v1/activation-requests/'.$proof['request_id'].'/acknowledge', ['request_token' => $proof['request_token'], 'installation_uuid' => $uuid, 'entitlement_fingerprint' => $fingerprint])->assertJson(['status' => 'completed']);
        $this->assertNull($a->fresh()->signed_entitlement);
    }

    public function test_security_boundaries(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/admin')->assertRedirect();
        $u = User::factory()->create(['is_admin' => false]);
        $this->actingAs($u)->get('/admin')->assertForbidden();
        $this->postJson('/api/v1/activation-requests', ['product' => 'unknown'])->assertUnprocessable();
        $this->postJson('/api/v1/activation-requests', array_fill_keys(['x'], str_repeat('x', 9000)))->assertStatus(413);
    }

    private function decode(string $v): string
    {
        return base64_decode(strtr($v, '-_', '+/').str_repeat('=', (4 - strlen($v) % 4) % 4));
    }
}
