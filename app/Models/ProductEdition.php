<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductEdition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['update_entitlement' => 'boolean', 'support_entitlement' => 'boolean'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
