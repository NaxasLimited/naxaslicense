<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Product extends Model { protected $guarded=[]; public function editions(){return $this->hasMany(ProductEdition::class);} }
