<?php
namespace App\Services\Signing; interface LicenseSignerInterface { public function sign(array $payload):string; }
