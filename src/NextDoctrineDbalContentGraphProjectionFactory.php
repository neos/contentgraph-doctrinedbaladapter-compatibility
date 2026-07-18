<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph\ContentGraphReadModelAdapter;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph\ContentGraphTableNames;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph\Domain\Repository\ContentStreamLayerFinder;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph\Domain\Repository\DimensionSpacePointsRepository;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph\Domain\Repository\NodeFactory;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph\Domain\Repository\ProjectionContentGraph;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\Dbal\MysqlPlatformContentRepositoryLocker;
use Neos\ContentRepository\Core\Factory\SubscriberFactoryDependencies;
use Neos\ContentRepository\Core\Projection\ProjectionFactoryInterface;

/**
 * @internal only for testing
 * @implements ProjectionFactoryInterface<NextDoctrineDbalContentGraphProjection>
 */
final class NextDoctrineDbalContentGraphProjectionFactory implements ProjectionFactoryInterface
{
    public function __construct(
        private readonly Connection $dbal,
    ) {
    }

    public function build(
        SubscriberFactoryDependencies $projectionFactoryDependencies,
        array $options,
    ): NextDoctrineDbalContentGraphProjection {
        if (!$this->dbal->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            throw new \RuntimeException(sprintf('Cannot build content graph for non mariadb/mysql connection %s', $this->dbal->getDatabasePlatform()::class), 1780672272);
        }

        $tableNames = ContentGraphTableNames::create(
            $projectionFactoryDependencies->contentRepositoryId
        );

        $dimensionSpacePointsRepository = new DimensionSpacePointsRepository($this->dbal, $tableNames);
        $contentStreamLayerFinder = new ContentStreamLayerFinder($this->dbal, $tableNames);

        $nodeFactory = new NodeFactory(
            $projectionFactoryDependencies->contentRepositoryId,
            $projectionFactoryDependencies->getPropertyConverter(),
            $dimensionSpacePointsRepository
        );

        $contentGraphReadModel = new ContentGraphReadModelAdapter(
            $this->dbal,
            $nodeFactory,
            $projectionFactoryDependencies->contentRepositoryId,
            $projectionFactoryDependencies->nodeTypeManager,
            $tableNames
        );

        return new NextDoctrineDbalContentGraphProjection(
            $this->dbal,
            MysqlPlatformContentRepositoryLocker::forContentRepositoryAndConnection(
                $projectionFactoryDependencies->contentRepositoryId,
                $this->dbal
            ),
            new ProjectionContentGraph(
                $this->dbal,
                $tableNames
            ),
            $tableNames,
            $dimensionSpacePointsRepository,
            $contentStreamLayerFinder,
            $contentGraphReadModel
        );
    }
}
