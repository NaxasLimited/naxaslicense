<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class LicenseActivation extends Model { protected $guarded=[]; protected function casts():array{return ['activated_at'=>'datetime'];} }
