<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Authentication;

use Lmarcho\CommerceMcp\Api\AuthenticationServiceInterface;
use Magento\Framework\App\ResourceConnection;

class AuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly TokenGenerator $tokenGenerator
    ) {
    }

    public function authenticate(string $plainToken): ?AuthenticatedClient
    {
        if ($plainToken === '' || strlen($plainToken) > 256) {
            return null;
        }

        $connection = $this->resourceConnection->getConnection();
        $tokenTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_access_token');
        $clientTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_client');
        $roleToolTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_role_tool');

        $select = $connection->select()
            ->from(['token' => $tokenTable], ['token_id'])
            ->join(
                ['client' => $clientTable],
                'client.client_id = token.client_id',
                ['client_id', 'name', 'role_id']
            )
            ->where('token.token_hash = ?', $this->tokenGenerator->hash($plainToken))
            ->where('token.revoked_at IS NULL')
            ->where('(token.expires_at IS NULL OR token.expires_at > UTC_TIMESTAMP())')
            ->where('client.is_active = ?', 1)
            ->limit(1);

        $row = $connection->fetchRow($select);
        if (!is_array($row)) {
            return null;
        }

        $tools = $connection->fetchCol(
            $connection->select()
                ->from($roleToolTable, ['tool_name'])
                ->where('role_id = ?', (int)$row['role_id'])
                ->order('tool_name ASC')
        );

        $connection->update(
            $tokenTable,
            ['last_used_at' => gmdate('Y-m-d H:i:s')],
            ['token_id = ?' => (int)$row['token_id']]
        );

        return new AuthenticatedClient(
            (int)$row['client_id'],
            (string)$row['name'],
            array_values(array_map('strval', $tools))
        );
    }
}
