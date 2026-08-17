<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\ProductEdition;
use App\Services\DomainNormalizer;
use App\Services\Signing\LicenseSignerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'requests' => ActivationRequest::with(['product', 'edition'])->latest()->limit(8)->get(),
            'pendingCount' => ActivationRequest::where('status', 'pending')->count(),
            'activeLicenseCount' => License::where('status', 'active')->count(),
            'activeInstallationCount' => LicenseActivation::where('status', 'active')->count(),
            'customerCount' => Customer::where('status', 'active')->count(),
        ]);
    }

    public function requests()
    {
        return view('admin.requests', ['requests' => ActivationRequest::with(['product', 'edition'])->latest()->paginate()]);
    }

    public function show(ActivationRequest $activationRequest)
    {
        return view('admin.request', [
            'activationRequest' => $activationRequest->load(['product', 'edition']),
            'licenses' => License::with('customer')
                ->where('status', 'active')
                ->where('product_id', $activationRequest->product_id)
                ->where('product_edition_id', $activationRequest->product_edition_id)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->get(),
        ]);
    }

    public function customers()
    {
        return view('admin.customers', ['customers' => Customer::latest()->get()]);
    }

    public function storeCustomer(Request $r)
    {
        $c = Customer::create($r->validate(['name' => 'required|max:255', 'email' => 'required|email|max:255', 'company' => 'nullable|max:255']));
        $this->audit('customer.created', $c);

        return back()->with('status', 'Customer created successfully.');
    }

    public function licenses()
    {
        return view('admin.licenses', ['licenses' => License::with(['customer', 'activations'])->latest()->get(), 'customers' => Customer::where('status', 'active')->get(), 'editions' => ProductEdition::with('product')->where('status', 'active')->get()]);
    }

    public function storeLicense(Request $r)
    {
        $v = $r->validate(['customer_id' => 'required|exists:customers,id', 'product_edition_id' => 'required|exists:product_editions,id', 'expires_at' => 'nullable|date|after:today']);
        $e = ProductEdition::with('product')->findOrFail($v['product_edition_id']);
        $l = License::create(['license_id' => 'NAXAS-BLD-'.strtoupper(Str::random(8)), 'customer_id' => $v['customer_id'], 'product_id' => $e->product_id, 'product_edition_id' => $e->id, 'status' => 'active', 'license_type' => $e->license_type, 'production_domain_limit' => $e->production_domain_limit, 'update_entitlement' => $e->update_entitlement, 'support_entitlement' => $e->support_entitlement, 'issued_at' => now(), 'expires_at' => $v['expires_at'] ?? null]);
        $this->audit('license.created', $l);

        return back()->with('status', 'License created successfully.');
    }

    public function activate(License $license)
    {
        if ($license->status !== 'suspended') {
            throw ValidationException::withMessages(['license' => 'Only a suspended license can be reactivated.']);
        }
        if ($license->expires_at?->isPast()) {
            throw ValidationException::withMessages(['license' => 'An expired license cannot be reactivated.']);
        }
        DB::transaction(function () use ($license) {
            $license->update(['status' => 'active', 'suspended_at' => null]);
            $license->activations()->where('status', 'suspended')->update(['status' => 'active']);
            $this->audit('license.reactivated', $license);
        });

        return back();
    }

    public function suspend(License $license)
    {
        if ($license->status !== 'active') {
            throw ValidationException::withMessages(['license' => 'Only an active license can be suspended.']);
        }
        DB::transaction(function () use ($license) {
            $license->update(['status' => 'suspended', 'suspended_at' => now()]);
            $license->activations()->where('status', 'active')->update(['status' => 'suspended']);
            $this->audit('license.suspended', $license);
        });

        return back();
    }

    public function revoke(Request $r, License $license)
    {
        $v = $r->validate(['reason' => 'required|string|max:500']);
        if ($license->status === 'revoked') {
            throw ValidationException::withMessages(['license' => 'The license is already revoked.']);
        }
        DB::transaction(function () use ($license, $v) {
            $license->update(['status' => 'revoked', 'revoked_at' => now(), 'revocation_reason' => $v['reason']]);
            $license->activations()->whereIn('status', ['active', 'suspended'])->update(['status' => 'revoked', 'deactivated_at' => now()]);
            $this->audit('license.revoked', $license);
        });

        return back();
    }

    public function deactivate(LicenseActivation $activation)
    {
        if (! in_array($activation->status, ['active', 'suspended'], true)) {
            throw ValidationException::withMessages(['activation' => 'The installation is already inactive.']);
        }
        $activation->update(['status' => 'deactivated', 'deactivated_at' => now()]);
        $this->audit('activation.deactivated', $activation);

        return back();
    }

    public function approve(Request $r, ActivationRequest $activationRequest, LicenseSignerInterface $signer, DomainNormalizer $domains)
    {
        $r->validate(['license_id' => 'required|exists:licenses,id']);
        DB::transaction(function () use ($r, $activationRequest, $signer, $domains) {
            $a = ActivationRequest::lockForUpdate()->findOrFail($activationRequest->id);
            if ($a->status !== 'pending' || $a->expires_at->isPast()) {
                throw ValidationException::withMessages(['request' => 'Request is no longer pending.']);
            }$l = License::with(['customer', 'product', 'edition', 'activations'])->lockForUpdate()->findOrFail($r->integer('license_id'));
            if ($l->status !== 'active' || $l->expires_at?->isPast() || $l->product_id !== $a->product_id || $l->product_edition_id !== $a->product_edition_id) {
                throw ValidationException::withMessages(['license_id' => 'License is not eligible.']);
            }$canonical = $domains->canonical($a->normalized_domain);
            $production = $domains->isNonProduction($a->normalized_domain) ? null : $canonical;
            $used = $l->activations->where('status', 'active')->filter(fn ($x) => ! $domains->isNonProduction($x->normalized_domain))->map(fn ($x) => $domains->canonical($x->normalized_domain))->unique();
            if ($production && ! $used->contains($production) && $used->count() >= $l->production_domain_limit) {
                throw ValidationException::withMessages(['license_id' => 'Production domain capacity exceeded.']);
            }$activation = LicenseActivation::firstOrCreate(['activation_request_id' => $a->id], ['license_id' => $l->id, 'installation_uuid' => $a->installation_uuid, 'normalized_domain' => $a->normalized_domain, 'domain_hash' => hash('sha256', $canonical), 'environment' => $a->environment, 'status' => 'active', 'activated_at' => now()]);
            $payload = [
                'schema_version' => 1,
                'key_id' => config('license.key_id'),
                'license_id' => $l->license_id,
                'issuer' => config('license.issuer'),
                'product' => $l->product->slug,
                'type' => $l->license_type,
                'license_type' => $l->license_type,
                'status' => 'active',
                'customer_name' => $l->customer->name,
                'customer_email' => $l->customer->email,
                'domain' => $a->normalized_domain,
                'production_domain' => $production,
                'allowed_non_production_hosts' => ['localhost', '127.0.0.1', '::1', '*.test', '*.local'],
                'installation_uuid' => $a->installation_uuid,
                'issued_at' => $l->issued_at->toIso8601String(),
                'expires_at' => $l->expires_at?->toIso8601String(),
                'update_entitlement' => $l->update_entitlement,
                'support_entitlement' => $l->support_entitlement,
                'support_expires_at' => $l->support_expires_at?->toIso8601String(),
                'entitlements' => ['updates' => $l->update_entitlement, 'support' => $l->support_entitlement],
            ];
            $signed = $signer->sign($payload);
            $a->forceFill(['license_id' => $l->id, 'signed_entitlement' => $signed, 'entitlement_fingerprint' => hash('sha256', $signed), 'status' => 'approved', 'approved_at' => now()])->save();
            $this->audit('license.signed', $l);
            $this->audit('activation.approved', $a);
        });

        return redirect()->route('admin.requests.show', $activationRequest);
    }

    public function reject(Request $r, ActivationRequest $activationRequest)
    {
        $v = $r->validate(['reason' => 'required|max:255']);
        abort_unless($activationRequest->status === 'pending', 409);
        $activationRequest->update(['status' => 'rejected', 'rejected_at' => now(), 'failure_code' => 'ADMIN_REJECTED', 'safe_failure_message' => $v['reason']]);
        $this->audit('activation.rejected', $activationRequest);

        return back();
    }

    public function expire(ActivationRequest $activationRequest)
    {
        abort_unless($activationRequest->status === 'pending', 409);
        $activationRequest->update(['status' => 'expired']);
        $this->audit('activation.expired', $activationRequest);

        return back();
    }

    private function audit(string $action, $m): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'entity_type' => class_basename($m), 'entity_id' => (string) $m->getKey(), 'safe_summary' => str_replace('.', ' ', $action), 'correlation_id' => (string) Str::uuid()]);
    }
}
