<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Authentication;

use Lmarcho\CommerceMcp\Model\Mcp\ToolRegistry;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;

class ClientManager
{
    public const READ_ONLY_ROLE = 'Lmarcho Chat Read Only';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly TokenGenerator $tokenGenerator,
        private readonly ToolRegistry $toolRegistry
    ) {
    }

    public function create(string $name, ?string $expiresAt = null): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Client name is required.');
        }

        $connection = $this->resourceConnection->getConnection();
        $clientTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_client');
        if ($connection->fetchOne(
            $connection->select()->from($clientTable, ['client_id'])->where('name = ?', $name)
        )) {
            throw new AlreadyExistsException(__('Commerce MCP client already exists.'));
        }

        $connection->beginTransaction();
        try {
            $roleId = $this->ensureReadOnlyRole();
            $connection->insert($clientTable, [
                'name' => $name,
                'role_id' => $roleId,
                'is_active' => 1,
            ]);
            $clientId = (int)$connection->lastInsertId($clientTable);
            $token = $this->insertToken($clientId, $expiresAt);
            $connection->commit();
            return $token;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function rotate(string $name, ?string $expiresAt = null): string
    {
        $connection = $this->resourceConnection->getConnection();
        $clientId = $this->getClientId($name);
        $tokenTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_access_token');

        $connection->beginTransaction();
        try {
            $connection->update(
                $tokenTable,
                ['revoked_at' => gmdate('Y-m-d H:i:s')],
                ['client_id = ?' => $clientId, 'revoked_at IS NULL']
            );
            $token = $this->insertToken($clientId, $expiresAt);
            $connection->commit();
            return $token;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function revoke(string $name): void
    {
        $connection = $this->resourceConnection->getConnection();
        $clientId = $this->getClientId($name);
        $clientTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_client');
        $tokenTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_access_token');
        $now = gmdate('Y-m-d H:i:s');

        $connection->beginTransaction();
        try {
            $connection->update($clientTable, ['is_active' => 0], ['client_id = ?' => $clientId]);
            $connection->update(
                $tokenTable,
                ['revoked_at' => $now],
                ['client_id = ?' => $clientId, 'revoked_at IS NULL']
            );
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function ensureReadOnlyRole(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $roleTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_role');
        $roleToolTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_role_tool');
        $roleId = $connection->fetchOne(
            $connection->select()->from($roleTable, ['role_id'])->where('name = ?', self::READ_ONLY_ROLE)
        );

        if (!$roleId) {
            $connection->insert($roleTable, ['name' => self::READ_ONLY_ROLE]);
            $roleId = (int)$connection->lastInsertId($roleTable);
        }

        foreach ($this->toolRegistry->names() as $toolName) {
            $connection->insertOnDuplicate(
                $roleToolTable,
                ['role_id' => (int)$roleId, 'tool_name' => $toolName],
                ['tool_name']
            );
        }

        return (int)$roleId;
    }

    private function insertToken(int $clientId, ?string $expiresAt): string
    {
        $token = $this->tokenGenerator->generate();
        $this->resourceConnection->getConnection()->insert(
            $this->resourceConnection->getTableName('lmarcho_commerce_mcp_access_token'),
            [
                'client_id' => $clientId,
                'token_hash' => $this->tokenGenerator->hash($token),
                'expires_at' => $expiresAt,
            ]
        );
        return $token;
    }

    private function getClientId(string $name): int
    {
        $connection = $this->resourceConnection->getConnection();
        $clientTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_client');
        $clientId = $connection->fetchOne(
            $connection->select()->from($clientTable, ['client_id'])->where('name = ?', trim($name))
        );
        if (!$clientId) {
            throw new NoSuchEntityException(__('Commerce MCP client does not exist.'));
        }
        return (int)$clientId;
    }
}
