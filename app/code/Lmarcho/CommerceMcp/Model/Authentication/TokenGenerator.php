<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Authentication;

class TokenGenerator
{
    public function generate(): string
    {
        return 'lmcp_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
