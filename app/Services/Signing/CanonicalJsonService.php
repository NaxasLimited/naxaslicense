<?php
namespace App\Services\Signing; use JsonException;
class CanonicalJsonService { public function encode(array $value):string {$value=$this->sort($value);return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);} private function sort(array $v):array {if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as $k=>$x)if(is_array($x))$v[$k]=$this->sort($x);return $v;} }
