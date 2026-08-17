<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'expires_at' => 'datetime', 'support_expires_at' => 'datetime', 'suspended_at' => 'datetime', 'revoked_at' => 'datetime', 'update_entitlement' => 'boolean', 'support_entitlement' => 'boolean'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function edition()
    {
        return $this->belongsTo(ProductEdition::class, 'product_edition_id');
    }

    public function activations()
    {
        return $this->hasMany(LicenseActivation::class);
    }
}
