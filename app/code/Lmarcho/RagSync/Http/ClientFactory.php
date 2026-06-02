<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Http;

use GuzzleHttp\Client;

class ClientFactory
{
    /**
     * Create Guzzle HTTP client
     *
     * @param array $data
     * @return Client
     */
    public function create(array $data = []): Client
    {
        $config = $data['config'] ?? [];

        return new Client($config);
    }
}
