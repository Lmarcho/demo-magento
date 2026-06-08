<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Service;

use Magento\Framework\Math\Random;

class CorrelationId
{
    public function __construct(private readonly Random $random)
    {
    }

    public function resolve(?string $provided): string
    {
        if ($provided !== null && preg_match('/\A[A-Za-z0-9._:-]{8,128}\z/', $provided) === 1) {
            return $provided;
        }

        return $this->random->getRandomString(32);
    }
}
