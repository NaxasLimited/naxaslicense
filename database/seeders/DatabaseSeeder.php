<?php
namespace Database\Seeders; use App\Models\{Product,ProductEdition}; use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {public function run():void{$p=Product::updateOrCreate(['slug'=>'buildora-cms'],['name'=>'Buildora CMS','status'=>'active']);ProductEdition::updateOrCreate(['product_id'=>$p->id,'slug'=>'single-site'],['name'=>'Single Site','license_type'=>'single_site','production_domain_limit'=>1,'update_entitlement'=>true,'support_entitlement'=>false,'support_duration_months'=>null,'status'=>'active']);}}
