<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseActivation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'deactivated_at' => 'datetime'];
    }

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function request()
    {
        return $this->belongsTo(ActivationRequest::class, 'activation_request_id');
    }
}
