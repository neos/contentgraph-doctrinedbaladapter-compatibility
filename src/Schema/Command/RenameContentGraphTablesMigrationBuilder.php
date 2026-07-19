<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Schema\Command;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;

class RenameContentGraphTablesMigrationBuilder
{
    public function __construct(
        public string $migrationVersion,
        public ContentRepositoryId $contentRepositoryId,
    ) {
    }

    public function build(): string
    {
        $cr = $this->contentRepositoryId->value;

        return <<<PHP
        <?php
        
        declare(strict_types=1);
        
        namespace Neos\Flow\Persistence\Doctrine\Migrations;
        
        use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
        use Doctrine\DBAL\Schema\Schema;
        use Doctrine\Migrations\AbstractMigration;
        
        final class Version{$this->migrationVersion} extends AbstractMigration
        {
            public function getDescription(): string
            {
                return 'Copies the pre-patched Neos 9.2 content graph tables as the new default for content repository "{$cr}"';
            }
        
            public function up(Schema \$schema): void
            {
                \$this->abortIf(
                    !\$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
                    "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\AbstractMySQLPlatform'."
                );
        
                if (\$schema->hasTable('cr_{$cr}_p_92_graph_node')) {
                    \$this->addSql(<<<SQL
                    RENAME TABLE
                      # keep current tables as (cr_{$cr}_p_90_...)
                      cr_{$cr}_p_graph_contentstream TO cr_{$cr}_p_90_graph_contentstream,
                      cr_{$cr}_p_graph_dimensionspacepoints TO cr_{$cr}_p_90_graph_dimensionspacepoints,
                      cr_{$cr}_p_graph_hierarchyrelation TO cr_{$cr}_p_90_graph_hierarchyrelation,
                      cr_{$cr}_p_graph_node TO cr_{$cr}_p_90_graph_node,
                      cr_{$cr}_p_graph_referencerelation TO cr_{$cr}_p_90_graph_referencerelation,
                      cr_{$cr}_p_graph_workspace TO cr_{$cr}_p_90_graph_workspace,
                    
                      # rename new tables to current (cr_{$cr}_p_...)
                      cr_{$cr}_p_92_graph_contentstream TO cr_{$cr}_p_graph_contentstream,
                      cr_{$cr}_p_92_graph_contentstreamlayer TO cr_{$cr}_p_graph_contentstreamlayer,
                      cr_{$cr}_p_92_graph_dimensionspacepoints TO cr_{$cr}_p_graph_dimensionspacepoints,
                      cr_{$cr}_p_92_graph_hierarchyrelation TO cr_{$cr}_p_graph_hierarchyrelation,
                      cr_{$cr}_p_92_graph_node TO cr_{$cr}_p_graph_node,
                      cr_{$cr}_p_92_graph_referencerelation TO cr_{$cr}_p_graph_referencerelation,
                      cr_{$cr}_p_92_graph_workspace TO cr_{$cr}_p_graph_workspace
                    SQL);
                }
            }
        
            public function down(Schema \$schema): void
            {
                \$this->abortIf(
                    !\$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
                    "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\AbstractMySQLPlatform'."
                );
        
                if (\$schema->hasTable('cr_{$cr}_p_90_graph_node')) {
                    \$this->addSql(<<<SQL
                    RENAME TABLE
                      # keep new tables as (cr_{$cr}_p_92...)
                      cr_{$cr}_p_graph_contentstream TO cr_{$cr}_p_92_graph_contentstream,
                      cr_{$cr}_p_graph_contentstreamlayer TO cr_{$cr}_p_92_graph_contentstreamlayer,
                      cr_{$cr}_p_graph_dimensionspacepoints TO cr_{$cr}_p_92_graph_dimensionspacepoints,
                      cr_{$cr}_p_graph_hierarchyrelation TO cr_{$cr}_p_92_graph_hierarchyrelation,
                      cr_{$cr}_p_graph_node TO cr_{$cr}_p_92_graph_node,
                      cr_{$cr}_p_graph_referencerelation TO cr_{$cr}_p_92_graph_referencerelation,
                      cr_{$cr}_p_graph_workspace TO cr_{$cr}_p_92_graph_workspace,
                           
                      # rename old tables to current (cr_{$cr}_p_...)
                      cr_{$cr}_p_90_graph_contentstream TO cr_{$cr}_p_graph_contentstream,
                      cr_{$cr}_p_90_graph_dimensionspacepoints TO cr_{$cr}_p_graph_dimensionspacepoints,
                      cr_{$cr}_p_90_graph_hierarchyrelation TO cr_{$cr}_p_graph_hierarchyrelation,
                      cr_{$cr}_p_90_graph_node TO cr_{$cr}_p_graph_node,
                      cr_{$cr}_p_90_graph_referencerelation TO cr_{$cr}_p_graph_referencerelation,
                      cr_{$cr}_p_90_graph_workspace TO cr_{$cr}_p_graph_workspace
                    SQL);
                }
            }
        }
        PHP;
    }
}
