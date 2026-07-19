<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

class TestingDoctrineMigrationRunner
{
    /**
     * @param class-string<AbstractMigration> $className
     */
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private string $className,
    ) {
    }

    public function executeUp(): void
    {
        $migration = new $this->className(
            $this->connection,
            $this->logger
        );

        $schema = $this->connection->createSchemaManager()->introspectSchema();

        $migration->up($schema);

        foreach ($migration->getSql() as $query) {
            $this->connection->executeQuery($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }
}
