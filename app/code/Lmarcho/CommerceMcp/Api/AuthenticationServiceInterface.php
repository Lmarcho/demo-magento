<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

use Lmarcho\CommerceMcp\Model\Authentication\AuthenticatedClient;

interface AuthenticationServiceInterface
{
    public function authenticate(string $plainToken): ?AuthenticatedClient;
}
