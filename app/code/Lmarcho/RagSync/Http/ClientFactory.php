<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Http;

use GuzzleHttp\Client;
use Magento\Framework\ObjectManagerInterface;

class ClientFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

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
