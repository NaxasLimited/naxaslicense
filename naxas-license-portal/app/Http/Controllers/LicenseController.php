<?php

namespace App\Http\Controllers;

use App\Models\LicenseState;
use App\Services\Licensing\DomainNormalizer;
use App\Services\Licensing\EntitlementVerifier;
use App\Services\Licensing\PortalClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class LicenseController extends Controller
{
    public function show(): View
    {
        return view('license', ['licenseState' => LicenseState::first()]);
    }

    public function create(Request $request, PortalClient $portal, DomainNormalizer $domains): RedirectResponse
    {
        $state = $this->state();
        if ($state->request_status === 'pending' && $state->request_expires_at?->isFuture()) {
            return back()->with('status', 'An activation request is already pending.');
        }

        try {
            $response = $portal->create([
                'product' => config('licensing.product'), 'version' => config('app.version', '1.0.0'),
                'license_type' => config('licensing.license_type'), 'installation_uuid' => $state->installation_uuid,
                'domain' => $domains->normalize($request->getHost()), 'environment' => app()->environment(),
                'nonce' => Str::random(48),
            ]);
            foreach (['request_id', 'request_token', 'status', 'expires_at', 'portal_url'] as $field) {
                throw_unless(isset($response[$field]) && is_string($response[$field]), RuntimeException::class, 'The portal response is incomplete.');
            }
            $state->update(['request_id' => $response['request_id'], 'request_token' => $response['request_token'],
                'request_status' => $response['status'], 'request_expires_at' => $response['expires_at'],
                'last_error_code' => null, 'last_safe_message' => null]);

            return back()->with('status', 'Activation request generated. Keep the displayed token private.');
        } catch (RuntimeException $exception) {
            $state->update(['last_error_code' => 'PORTAL_UNAVAILABLE', 'last_safe_message' => $exception->getMessage()]);

            return back()->withErrors(['license' => $exception->getMessage()]);
        }
    }

    public function poll(Request $request, PortalClient $portal, EntitlementVerifier $verifier): RedirectResponse
    {
        $state = $this->state();
        abort_unless($state->request_id && $state->request_token, 409, 'There is no pending activation request.');

        try {
            $proof = ['request_token' => $state->request_token, 'installation_uuid' => $state->installation_uuid];
            $response = $portal->status($state->request_id, $proof);
            if (($response['status'] ?? null) !== 'approved') {
                $state->update(['request_status' => $response['status'] ?? 'error',
                    'last_error_code' => $response['failure_code'] ?? null, 'last_safe_message' => $response['safe_message'] ?? null]);

                return back()->with('status', 'Activation status: '.($response['status'] ?? 'unknown'));
            }
            $token = $response['signed_license'] ?? '';
            throw_unless(is_string($token) && $token !== '', RuntimeException::class, 'The approved response has no signed license.');
            $payload = $verifier->verify($token, $state->installation_uuid, $request->getHost());
            $fingerprint = hash('sha256', $token);
            $state->update(['signed_license' => $token, 'entitlement' => $payload,
                'entitlement_fingerprint' => $fingerprint, 'request_status' => 'active',
                'last_error_code' => null, 'last_safe_message' => null]);
            $portal->acknowledge($state->request_id, $proof + ['entitlement_fingerprint' => $fingerprint]);

            return back()->with('status', 'License activated and verified locally.');
        } catch (RuntimeException $exception) {
            $state->update(['last_error_code' => 'ACTIVATION_FAILED', 'last_safe_message' => $exception->getMessage()]);

            return back()->withErrors(['license' => $exception->getMessage()]);
        }
    }

    private function state(): LicenseState
    {
        return LicenseState::firstOrCreate([], ['installation_uuid' => (string) Str::uuid()]);
    }
}
