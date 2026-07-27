<?php
namespace App\Services\Signing; use RuntimeException;
class RsaLicenseSigner implements LicenseSignerInterface { public function __construct(private CanonicalJsonService $json,private SigningKeyResolver $keys){} public function sign(array $payload):string {$bytes=$this->json->encode($payload);if(!openssl_sign($bytes,$sig,$this->keys->resolve(),OPENSSL_ALGO_SHA256))throw new RuntimeException('Signing failed.');return $this->b64($bytes).'.'.$this->b64($sig);} private function b64(string $v):string{return rtrim(strtr(base64_encode($v),'+/','-_'),'=');} }
