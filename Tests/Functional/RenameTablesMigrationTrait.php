<?php

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Tests\Functional;

use Doctrine\DBAL\Connection;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Schema\Command\RenameContentGraphTablesMigrationBuilder;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Psr\Log\LoggerInterface;

trait RenameTablesMigrationTrait
{
    final protected function getRenameTablesMigration(ContentRepositoryId $contentRepositoryId): TestingDoctrineMigrationRunner
    {
        $migrationCode = (new RenameContentGraphTablesMigrationBuilder(
            migrationVersion: '20242209',
            contentRepositoryId: $contentRepositoryId
        ))->build();

        eval(substr($migrationCode, strlen('<?php')));

        $className = '\Neos\Flow\Persistence\Doctrine\Migrations\Version20242209';

        return new TestingDoctrineMigrationRunner(
            $this->getObject(Connection::class),
            $this->getMockBuilder(LoggerInterface::class)->getMock(),
            $className
        );
    }
}
