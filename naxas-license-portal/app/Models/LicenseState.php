<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseState extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'request_token' => 'encrypted',
            'signed_license' => 'encrypted',
            'entitlement' => 'encrypted:array',
            'request_expires_at' => 'datetime',
        ];
    }
}
