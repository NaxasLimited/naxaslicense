<?php

namespace Tests\Feature;

use App\Models\ActivationRequest;
use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private License $license;

    private LicenseActivation $activation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $product = Product::firstOrFail();
        $edition = $product->editions()->firstOrFail();
        $customer = Customer::create(['name' => 'Buyer', 'email' => 'buyer@example.com']);
        $this->license = License::create([
            'license_id' => 'NAXAS-BLD-OPERATE1', 'customer_id' => $customer->id,
            'product_id' => $product->id, 'product_edition_id' => $edition->id,
            'status' => 'active', 'license_type' => $edition->license_type,
            'production_domain_limit' => 1, 'update_entitlement' => true,
            'support_entitlement' => false, 'issued_at' => now(),
        ]);
        $request = ActivationRequest::forceCreate([
            'request_id' => fake()->uuid(), 'request_token_hash' => hash('sha256', 'secret-token'),
            'request_token_prefix' => 'secret-t', 'product_id' => $product->id,
            'product_edition_id' => $edition->id, 'license_id' => $this->license->id,
            'installation_uuid' => fake()->uuid(), 'normalized_domain' => 'example.com',
            'environment' => 'production', 'application_version' => '1.0.0',
            'nonce_hash' => hash('sha256', 'nonce'), 'status' => 'completed',
            'expires_at' => now()->addHour(),
        ]);
        $this->activation = LicenseActivation::create([
            'license_id' => $this->license->id, 'activation_request_id' => $request->id,
            'installation_uuid' => $request->installation_uuid, 'normalized_domain' => 'example.com',
            'domain_hash' => hash('sha256', 'example.com'), 'environment' => 'production',
            'status' => 'active', 'activated_at' => now(),
        ]);
    }

    public function test_admin_can_suspend_and_reactivate_a_license(): void
    {
        $this->actingAs($this->admin)->post(route('admin.licenses.suspend', $this->license))->assertRedirect();
        $this->assertSame('suspended', $this->license->fresh()->status);
        $this->assertSame('suspended', $this->activation->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.licenses.activate', $this->license))->assertRedirect();
        $this->assertSame('active', $this->license->fresh()->status);
        $this->assertSame('active', $this->activation->fresh()->status);
    }

    public function test_admin_can_deactivate_an_installation_and_revoke_a_license(): void
    {
        $this->actingAs($this->admin)->post(route('admin.activations.deactivate', $this->activation))->assertRedirect();
        $this->assertSame('deactivated', $this->activation->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.licenses.revoke', $this->license), ['reason' => 'Refunded'])->assertRedirect();
        $this->assertSame('revoked', $this->license->fresh()->status);
        $this->assertSame('deactivated', $this->activation->fresh()->status);
    }

    public function test_public_lookup_requires_both_token_and_matching_domain(): void
    {
        $this->post('/activate', ['request_token' => 'secret-token', 'domain' => 'other.example'])
            ->assertUnprocessable()->assertSee('could not be found');
        $this->post('/activate', ['request_token' => 'secret-token', 'domain' => 'https://www.example.com/path'])
            ->assertOk()->assertSee('Completed');
    }
}
