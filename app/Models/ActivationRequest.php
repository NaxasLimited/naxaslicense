<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivationRequest extends Model
{
    protected $guarded = ['request_token_hash', 'signed_entitlement'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'signed_entitlement' => 'encrypted'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function edition()
    {
        return $this->belongsTo(ProductEdition::class, 'product_edition_id');
    }

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}
