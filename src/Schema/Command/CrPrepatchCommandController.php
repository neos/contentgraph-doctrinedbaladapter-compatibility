<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Schema\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Package\PackageManager;
use Neos\Utility\Files;

class CrPrepatchCommandController extends CommandController
{
    #[Flow\Inject()]
    protected PackageManager $packageManager;

    public function generateMigrationCommand(string $packageKey, string $contentRepository = 'default'): void
    {
        $package = $this->packageManager->getPackage($packageKey);

        $migrationVersion = (new \DateTimeImmutable())->format('YmdHis');

        $migrationCode = <<<PHP
        <?php
        
        declare(strict_types=1);
        
        namespace Neos\Flow\Persistence\Doctrine\Migrations;
        
        use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
        use Doctrine\DBAL\Schema\Schema;
        use Doctrine\Migrations\AbstractMigration;
        
        final class Version{$migrationVersion} extends AbstractMigration
        {
            public function getDescription(): string
            {
                return 'Copies the pre-patched Neos 9.2 content graph tables as the new default for content repository "{$contentRepository}"';
            }
        
            public function up(Schema \$schema): void
            {
                \$this->abortIf(
                    !\$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
                    "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\AbstractMySQLPlatform'."
                );
        
                if (\$schema->hasTable('cr_{$contentRepository}_p_92_graph_node')) {
                    \$this->addSql(<<<SQL
                    RENAME TABLE
                      # keep current tables as (cr_{$contentRepository}_p_90_...)
                      cr_{$contentRepository}_p_graph_contentstream TO cr_{$contentRepository}_p_90_graph_contentstream,
                      cr_{$contentRepository}_p_graph_dimensionspacepoints TO cr_{$contentRepository}_p_90_graph_dimensionspacepoints,
                      cr_{$contentRepository}_p_graph_hierarchyrelation TO cr_{$contentRepository}_p_90_graph_hierarchyrelation,
                      cr_{$contentRepository}_p_graph_node TO cr_{$contentRepository}_p_90_graph_node,
                      cr_{$contentRepository}_p_graph_referencerelation TO cr_{$contentRepository}_p_90_graph_referencerelation,
                      cr_{$contentRepository}_p_graph_workspace TO cr_{$contentRepository}_p_90_graph_workspace,
                    
                      # rename new tables to current (cr_{$contentRepository}_p_...)
                      cr_{$contentRepository}_p_92_graph_contentstream TO cr_{$contentRepository}_p_graph_contentstream,
                      cr_{$contentRepository}_p_92_graph_contentstreamlayer TO cr_{$contentRepository}_p_graph_contentstreamlayer,
                      cr_{$contentRepository}_p_92_graph_dimensionspacepoints TO cr_{$contentRepository}_p_graph_dimensionspacepoints,
                      cr_{$contentRepository}_p_92_graph_hierarchyrelation TO cr_{$contentRepository}_p_graph_hierarchyrelation,
                      cr_{$contentRepository}_p_92_graph_node TO cr_{$contentRepository}_p_graph_node,
                      cr_{$contentRepository}_p_92_graph_referencerelation TO cr_{$contentRepository}_p_graph_referencerelation,
                      cr_{$contentRepository}_p_92_graph_workspace TO cr_{$contentRepository}_p_graph_workspace
                    SQL);
                }
            }
        
            public function down(Schema \$schema): void
            {
                \$this->abortIf(
                    !\$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
                    "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\AbstractMySQLPlatform'."
                );
        
                if (\$schema->hasTable('cr_{$contentRepository}_p_90_graph_node')) {
                    \$this->addSql(<<<SQL
                    RENAME TABLE
                      # keep new tables as (cr_{$contentRepository}_p_92...)
                      cr_{$contentRepository}_p_graph_contentstream TO cr_{$contentRepository}_p_92_graph_contentstream,
                      cr_{$contentRepository}_p_graph_contentstreamlayer TO cr_{$contentRepository}_p_92_graph_contentstreamlayer,
                      cr_{$contentRepository}_p_graph_dimensionspacepoints TO cr_{$contentRepository}_p_92_graph_dimensionspacepoints,
                      cr_{$contentRepository}_p_graph_hierarchyrelation TO cr_{$contentRepository}_p_92_graph_hierarchyrelation,
                      cr_{$contentRepository}_p_graph_node TO cr_{$contentRepository}_p_92_graph_node,
                      cr_{$contentRepository}_p_graph_referencerelation TO cr_{$contentRepository}_p_92_graph_referencerelation,
                      cr_{$contentRepository}_p_graph_workspace TO cr_{$contentRepository}_p_92_graph_workspace
                           
                      # rename old tables to current (cr_{$contentRepository}_p_...)
                      cr_{$contentRepository}_p_90_graph_contentstream TO cr_{$contentRepository}_p_graph_contentstream,
                      cr_{$contentRepository}_p_90_graph_dimensionspacepoints TO cr_{$contentRepository}_p_graph_dimensionspacepoints,
                      cr_{$contentRepository}_p_90_graph_hierarchyrelation TO cr_{$contentRepository}_p_graph_hierarchyrelation,
                      cr_{$contentRepository}_p_90_graph_node TO cr_{$contentRepository}_p_graph_node,
                      cr_{$contentRepository}_p_90_graph_referencerelation TO cr_{$contentRepository}_p_graph_referencerelation,
                      cr_{$contentRepository}_p_90_graph_workspace TO cr_{$contentRepository}_p_graph_workspace,
                    SQL);
                }
            }
        }
        PHP;

        $filePath = Files::concatenatePaths([$package->getPackagePath(), 'Migrations/Mysql', 'Version' . $migrationVersion . '.php']);

        Files::createDirectoryRecursively(dirname($filePath));

        file_put_contents($filePath, $migrationCode);

        $this->outputLine('<success>Generated new migration version %s</success>', [$migrationVersion]);
        $this->outputLine('Wrote migration to <comment>%s</comment>', [str_replace(FLOW_PATH_ROOT, '', $filePath)]);
    }
}
