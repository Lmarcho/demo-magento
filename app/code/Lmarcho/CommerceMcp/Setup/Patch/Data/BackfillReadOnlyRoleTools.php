<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Setup\Patch\Data;

use Lmarcho\CommerceMcp\Model\Authentication\ClientManager;
use Lmarcho\CommerceMcp\Model\Mcp\ToolRegistry;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class BackfillReadOnlyRoleTools implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ResourceConnection $resourceConnection,
        private readonly ToolRegistry $toolRegistry
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        try {
            $connection = $this->resourceConnection->getConnection();
            $roleTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_role');
            $roleToolTable = $this->resourceConnection->getTableName('lmarcho_commerce_mcp_role_tool');

            $roleId = $connection->fetchOne(
                $connection->select()
                    ->from($roleTable, ['role_id'])
                    ->where('name = ?', ClientManager::READ_ONLY_ROLE)
            );

            if (! $roleId) {
                $connection->insert($roleTable, ['name' => ClientManager::READ_ONLY_ROLE]);
                $roleId = (int) $connection->lastInsertId($roleTable);
            }

            foreach ($this->toolRegistry->names() as $toolName) {
                $connection->insertOnDuplicate(
                    $roleToolTable,
                    ['role_id' => (int) $roleId, 'tool_name' => $toolName],
                    ['tool_name']
                );
            }
        } finally {
            $this->moduleDataSetup->getConnection()->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
