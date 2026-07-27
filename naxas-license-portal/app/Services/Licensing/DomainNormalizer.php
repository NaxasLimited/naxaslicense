<?php

namespace App\Services\Licensing;

use InvalidArgumentException;

class DomainNormalizer
{
    public function normalize(string $input): string
    {
        $candidate = str_contains($input, '://') ? strtolower(trim($input)) : 'https://'.strtolower(trim($input));
        $host = rtrim((string) parse_url($candidate, PHP_URL_HOST), '.');

        if ($host === '' || (! filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
            throw new InvalidArgumentException('The configured application domain is invalid.');
        }

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
