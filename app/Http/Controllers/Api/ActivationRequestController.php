<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\AuditLog;
use App\Models\Product;
use App\Services\DomainNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ActivationRequestController extends Controller
{
    public function store(Request $request, DomainNormalizer $domains): JsonResponse
    {
        if (strlen($request->getContent()) > 8192) {
            return response()->json(['message' => 'Payload too large.'], 413);
        }
        $validated = $request->validate([
            'product' => 'required|string|max:64', 'version' => 'required|string|max:64',
            'license_type' => 'required|string|max:64', 'installation_uuid' => 'required|uuid',
            'domain' => 'required|string|max:253',
            'environment' => 'required|in:production,staging,local,development,testing',
            'nonce' => 'required|string|min:16|max:128',
        ]);
        $product = Product::where(['slug' => $validated['product'], 'status' => 'active'])->first();
        $edition = $product?->editions()->where(['license_type' => $validated['license_type'], 'status' => 'active'])->first();
        if (! $edition) {
            throw ValidationException::withMessages(['product' => 'The product or edition is unavailable.']);
        }
        try {
            $domain = $domains->normalize($validated['domain']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['domain' => 'The domain is invalid.']);
        }
        $token = 'BRQ-'.implode('-', str_split(strtoupper(substr(strtr(base64_encode(random_bytes(9)), '+/', 'XZ'), 0, 12)), 4));
        $activation = new ActivationRequest;
        $activation->forceFill([
            'request_id' => (string) Str::uuid(), 'request_token_hash' => hash('sha256', $token),
            'request_token_prefix' => substr($token, 0, 8), 'product_id' => $product->id,
            'product_edition_id' => $edition->id, 'installation_uuid' => $validated['installation_uuid'],
            'normalized_domain' => $domain, 'environment' => $validated['environment'],
            'application_version' => $validated['version'], 'nonce_hash' => hash('sha256', $validated['nonce']),
            'status' => 'pending', 'expires_at' => now()->addMinutes(config('license.request_ttl')),
        ])->save();
        $this->audit('activation.request_created', $activation, 'Activation request created');

        return response()->json(['request_id' => $activation->request_id, 'request_token' => $token,
            'status' => 'pending', 'expires_at' => $activation->expires_at->toIso8601String(),
            'portal_url' => url('/activate')], 201);
    }

    public function status(Request $request, string $requestId): JsonResponse
    {
        $validated = $this->validateProof($request);
        $activation = ActivationRequest::with('license')->where('request_id', $requestId)->firstOrFail();
        if (! $this->proofMatches($activation, $validated)) {
            return response()->json(['message' => 'Invalid activation proof.'], 403);
        }
        if ($activation->status === 'pending' && $activation->expires_at->isPast()) {
            $activation->update(['status' => 'expired']);
        }
        if ($activation->status === 'approved') {
            if ($activation->approved_at->addHours(config('license.delivery_window_hours'))->isPast()) {
                return response()->json(['status' => 'delivery_expired', 'safe_message' => 'The entitlement delivery window has expired.']);
            }
            if ($activation->license->status !== 'active' || $activation->license->expires_at?->isPast()) {
                return response()->json(['status' => $activation->license->status === 'active' ? 'expired' : $activation->license->status,
                    'safe_message' => 'The assigned license is not active.']);
            }

            return response()->json(['status' => 'approved', 'signed_license' => $activation->signed_entitlement,
                'entitlement_fingerprint' => $activation->entitlement_fingerprint]);
        }
        if ($activation->status === 'rejected') {
            return response()->json(['status' => 'rejected', 'failure_code' => $activation->failure_code,
                'safe_message' => $activation->safe_failure_message]);
        }
        if ($activation->status === 'expired') {
            return response()->json(['status' => 'expired', 'safe_message' => 'The activation request has expired.']);
        }

        return response()->json(['status' => $activation->status]);
    }

    public function acknowledge(Request $request, string $requestId): JsonResponse
    {
        $validated = $request->validate([
            'request_token' => 'required|string|max:64', 'installation_uuid' => 'required|uuid',
            'entitlement_fingerprint' => 'required|string|size:64|regex:/^[a-f0-9]+$/',
        ]);

        return DB::transaction(function () use ($requestId, $validated): JsonResponse {
            $activation = ActivationRequest::where('request_id', $requestId)->lockForUpdate()->firstOrFail();
            if (! $this->proofMatches($activation, $validated)
                || ! $activation->entitlement_fingerprint
                || ! hash_equals($activation->entitlement_fingerprint, $validated['entitlement_fingerprint'])) {
                return response()->json(['message' => 'Invalid acknowledgement proof.'], 403);
            }
            if ($activation->status === 'completed') {
                return response()->json(['status' => 'completed']);
            }
            if ($activation->status !== 'approved') {
                return response()->json(['message' => 'The activation cannot be acknowledged.'], 409);
            }
            $activation->forceFill(['status' => 'completed', 'completed_at' => now(), 'signed_entitlement' => null])->save();
            $this->audit('activation.completed', $activation, 'Verified entitlement acknowledged');

            return response()->json(['status' => 'completed']);
        });
    }

    private function validateProof(Request $request): array
    {
        return $request->validate(['request_token' => 'required|string|max:64', 'installation_uuid' => 'required|uuid']);
    }

    private function proofMatches(ActivationRequest $activation, array $proof): bool
    {
        return hash_equals($activation->request_token_hash, hash('sha256', $proof['request_token']))
            && hash_equals(strtolower($activation->installation_uuid), strtolower($proof['installation_uuid']));
    }

    private function audit(string $action, ActivationRequest $activation, string $summary): void
    {
        AuditLog::create(['action' => $action, 'entity_type' => 'activation_request',
            'entity_id' => (string) $activation->id, 'safe_summary' => $summary,
            'correlation_id' => (string) Str::uuid()]);
    }
}
