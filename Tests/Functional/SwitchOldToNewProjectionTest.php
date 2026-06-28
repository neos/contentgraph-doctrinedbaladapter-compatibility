<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Tests\Functional;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Neos\ContentGraph\DoctrineDbalAdapter\ContentGraphReadModelAdapter;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceFactoryDependencies;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceFactoryInterface;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceInterface;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateRootWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateWorkspace;
use Neos\ContentRepository\Core\Projection\ProjectionStatus;
use Neos\ContentRepository\Core\Projection\ProjectionStatusType;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\Subscription\DetachedSubscriptionStatus;
use Neos\ContentRepository\Core\Subscription\Engine\SubscriptionEngine;
use Neos\ContentRepository\Core\Subscription\Engine\SubscriptionEngineCriteria;
use Neos\ContentRepository\Core\Subscription\ProjectionSubscriptionStatus;
use Neos\ContentRepository\Core\Subscription\SubscriptionId;
use Neos\ContentRepository\Core\Subscription\SubscriptionStatus;
use Neos\ContentRepository\Core\Subscription\SubscriptionStatusCollection;
use Neos\ContentRepository\TestSuite\Fakes\FakeContentDimensionSourceFactory;
use Neos\ContentRepository\TestSuite\Fakes\FakeNodeTypeManagerFactory;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\EventStore\EventStoreInterface;
use Neos\EventStore\Model\Event\SequenceNumber;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SwitchOldToNewProjectionTest extends TestCase
{
    protected static ContentRepositoryId $contentRepositoryId;

    protected ContentRepository $contentRepository;

    protected SubscriptionEngine $subscriptionEngine;

    protected EventStoreInterface $eventStore;

    public static function setUpBeforeClass(): void
    {
        static::$contentRepositoryId = ContentRepositoryId::fromString('t_compatibility');
    }

    public function setUp(): void
    {
        $this->dropDatabaseSchema($this->getObject(Connection::class), static::$contentRepositoryId);
    }

    /** @test */
    public function migrateFromOldToNew()
    {
        // 1.) only the old projection exists
        $this->configureContentRepositories(<<<YAML
        contentRepositories:
          t_compatibility:
            eventStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\EventStore\DoctrineEventStoreFactory
            nodeTypeManager:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeNodeTypeManagerFactory
            contentDimensionSource:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeContentDimensionSourceFactory
            authProvider:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeAuthProviderFactory
            clock:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\Clock\SystemClockFactory
            subscriptionStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\SubscriptionStore\SubscriptionStoreFactory
            propertyConverters: {}
            contentGraphProjection:
              factoryObjectName: Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\OldDoctrineDbalContentGraphProjectionFactory
              catchUpHooks: {}
        YAML);

        $this->eventStore->setup();
        $this->subscriptionEngine->setup();
        $this->subscriptionEngine->boot();

        self::assertEquals(
            SubscriptionStatusCollection::fromArray([
                ProjectionSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph'),
                    subscriptionStatus: SubscriptionStatus::ACTIVE,
                    subscriptionPosition: SequenceNumber::none(),
                    subscriptionError: null,
                    setupStatus: ProjectionStatus::ok(),
                )
            ]),
            $this->subscriptionEngine->subscriptionStatus()
        );

        $this->contentRepository->handle(CreateRootWorkspace::create(WorkspaceName::fromString('live'), ContentStreamId::fromString('cs-live')));

        self::assertEquals(
            SubscriptionStatusCollection::fromArray([
                ProjectionSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph'),
                    subscriptionStatus: SubscriptionStatus::ACTIVE,
                    subscriptionPosition: SequenceNumber::fromInteger(2),
                    subscriptionError: null,
                    setupStatus: ProjectionStatus::ok(),
                )
            ]),
            $this->subscriptionEngine->subscriptionStatus()
        );
        self::assertNotNull($this->contentRepository->findWorkspaceByName(WorkspaceName::fromString('live')));

        // 2.) the new projections is installed
        $this->configureContentRepositories(<<<YAML
        contentRepositories:
          t_compatibility:
            eventStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\EventStore\DoctrineEventStoreFactory
            nodeTypeManager:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeNodeTypeManagerFactory
            contentDimensionSource:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeContentDimensionSourceFactory
            authProvider:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeAuthProviderFactory
            clock:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\Clock\SystemClockFactory
            subscriptionStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\SubscriptionStore\SubscriptionStoreFactory
            propertyConverters: {}
            contentGraphProjection:
              factoryObjectName: Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\OldDoctrineDbalContentGraphProjectionFactory
              catchUpHooks: {}
            projections:
              'contentGraph_v1':
                factoryObjectName: Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\NewDoctrineDbalContentGraphProjectionFactory
                options:
                  # must align with the version above and with the version of ContentGraphProjectionFactoryInterface::getSubscriptionId()
                  requiredGraphSubscriptionId: 'contentGraph_v1'
        YAML);

        $newContentGraphSubscriptionStatus = $this->subscriptionEngine->subscriptionStatus(SubscriptionEngineCriteria::create(['contentGraph_v1']))->first();

        // NEW is discovered
        self::assertEquals('contentGraph_v1', $newContentGraphSubscriptionStatus?->subscriptionId->value);
        self::assertEquals(0, $newContentGraphSubscriptionStatus?->subscriptionPosition->value);
        self::assertEquals(
            SubscriptionStatus::NEW,
            $newContentGraphSubscriptionStatus->subscriptionStatus,
        );
        self::assertEquals(
            ProjectionStatusType::SETUP_REQUIRED,
            $newContentGraphSubscriptionStatus->setupStatus->type,
        );
        self::assertEquals(
            SubscriptionStatusCollection::fromArray([
                ProjectionSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph'),
                    subscriptionStatus: SubscriptionStatus::ACTIVE,
                    subscriptionPosition: SequenceNumber::fromInteger(2),
                    subscriptionError: null,
                    setupStatus: ProjectionStatus::ok(),
                )
            ]),
            $this->subscriptionEngine->subscriptionStatus(SubscriptionEngineCriteria::create(['contentGraph']))
        );

        // main content graph still works with NEW
        $this->contentRepository->handle(CreateWorkspace::create(WorkspaceName::fromString('user-one'), WorkspaceName::fromString('live'), ContentStreamId::fromString('cs-user-one')));
        self::assertNotNull($this->contentRepository->findWorkspaceByName(WorkspaceName::fromString('user-one')));

        // setup new graph
        $this->subscriptionEngine->setup();

        self::assertEquals(
            SubscriptionStatusCollection::fromArray([
                ProjectionSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph'),
                    subscriptionStatus: SubscriptionStatus::ACTIVE,
                    subscriptionPosition: SequenceNumber::fromInteger(4),
                    subscriptionError: null,
                    setupStatus: ProjectionStatus::ok(),
                ),
                ProjectionSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph_v1'),
                    subscriptionStatus: SubscriptionStatus::BOOTING,
                    subscriptionPosition: SequenceNumber::none(),
                    subscriptionError: null,
                    setupStatus: ProjectionStatus::ok(),
                )
            ]),
            $this->subscriptionEngine->subscriptionStatus()
        );

        // main content graph still works with BOOTING
        $this->contentRepository->handle(CreateWorkspace::create(WorkspaceName::fromString('user-two'), WorkspaceName::fromString('live'), ContentStreamId::fromString('cs-user-two')));
        self::assertNotNull($this->contentRepository->findWorkspaceByName(WorkspaceName::fromString('user-two')));

        // new content graph is empty
        $newContentGraphReadModel = $this->contentRepository->projectionState(ContentGraphReadModelAdapter::class);
        self::assertNull($newContentGraphReadModel->findWorkspaceByName(WorkspaceName::fromString('live')));
        self::assertCount(0, $newContentGraphReadModel->findWorkspaces());

        // 3. Replay new content graph
        $this->subscriptionEngine->boot(SubscriptionEngineCriteria::create(['contentGraph_v1']));
        self::assertEquals(
            SubscriptionStatusCollection::fromArray([
                ProjectionSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph'),
                    subscriptionStatus: SubscriptionStatus::ACTIVE,
                    subscriptionPosition: SequenceNumber::fromInteger(6),
                    subscriptionError: null,
                    setupStatus: ProjectionStatus::ok(),
                ),
                ProjectionSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph_v1'),
                    subscriptionStatus: SubscriptionStatus::ACTIVE,
                    subscriptionPosition: SequenceNumber::fromInteger(6),
                    subscriptionError: null,
                    setupStatus: ProjectionStatus::ok(),
                )
            ]),
            $this->subscriptionEngine->subscriptionStatus()
        );
        // new content graph can be queried
        $newContentGraphReadModel = $this->contentRepository->projectionState(ContentGraphReadModelAdapter::class);
        self::assertNotNull($newContentGraphReadModel->findWorkspaceByName(WorkspaceName::fromString('live')));
        self::assertNotNull($newContentGraphReadModel->findWorkspaceByName(WorkspaceName::fromString('user-two')));

        // both new and old content graph are caught up
        $newContentGraphReadModel = $this->contentRepository->projectionState(ContentGraphReadModelAdapter::class);
        $this->contentRepository->handle(CreateWorkspace::create(WorkspaceName::fromString('user-three'), WorkspaceName::fromString('live'), ContentStreamId::fromString('cs-user-three')));
        self::assertNotNull($this->contentRepository->findWorkspaceByName(WorkspaceName::fromString('user-three')));
        self::assertNotNull($newContentGraphReadModel->findWorkspaceByName(WorkspaceName::fromString('user-three')));

        // 4.) the old projections is uninstalled
        $this->configureContentRepositories(<<<YAML
        contentRepositories:
          t_compatibility:
            eventStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\EventStore\DoctrineEventStoreFactory
            nodeTypeManager:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeNodeTypeManagerFactory
            contentDimensionSource:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeContentDimensionSourceFactory
            authProvider:
              factoryObjectName: Neos\ContentRepository\TestSuite\Fakes\FakeAuthProviderFactory
            clock:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\Clock\SystemClockFactory
            subscriptionStore:
              factoryObjectName: Neos\ContentRepositoryRegistry\Factory\SubscriptionStore\SubscriptionStoreFactory
            propertyConverters: {}
            contentGraphProjection:
              factoryObjectName: Neos\ContentGraph\DoctrineDbalAdapter\DoctrineDbalContentGraphProjectionFactory
              catchUpHooks: {}
        YAML);

        // old projection will be marked as detached
        self::assertEquals(
            SubscriptionStatusCollection::fromArray([
                DetachedSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph'),
                    subscriptionStatus: SubscriptionStatus::ACTIVE,
                    subscriptionPosition: SequenceNumber::fromInteger(8),
                ),
                ProjectionSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph_v1'),
                    subscriptionStatus: SubscriptionStatus::ACTIVE,
                    subscriptionPosition: SequenceNumber::fromInteger(8),
                    subscriptionError: null,
                    setupStatus: ProjectionStatus::ok(),
                )
            ]),
            $this->subscriptionEngine->subscriptionStatus()
        );

        // only new content graph is caught up
        $this->contentRepository->handle(CreateWorkspace::create(WorkspaceName::fromString('user-four'), WorkspaceName::fromString('live'), ContentStreamId::fromString('cs-user-four')));
        self::assertNotNull($this->contentRepository->findWorkspaceByName(WorkspaceName::fromString('user-four')));

        // old projection is marked as detached
        self::assertEquals(
            SubscriptionStatusCollection::fromArray([
                DetachedSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph'),
                    subscriptionStatus: SubscriptionStatus::DETACHED,
                    subscriptionPosition: SequenceNumber::fromInteger(8),
                ),
                ProjectionSubscriptionStatus::create(
                    subscriptionId: SubscriptionId::fromString('contentGraph_v1'),
                    subscriptionStatus: SubscriptionStatus::ACTIVE,
                    subscriptionPosition: SequenceNumber::fromInteger(10),
                    subscriptionError: null,
                    setupStatus: ProjectionStatus::ok(),
                )
            ]),
            $this->subscriptionEngine->subscriptionStatus()
        );
    }

    final protected function configureContentRepositories(string $configuration): void
    {
        FakeNodeTypeManagerFactory::setConfiguration([]);
        FakeContentDimensionSourceFactory::setWithoutDimensions();
        $this->getObject(ContentRepositoryRegistry::class)->injectSettings(Yaml::parse($configuration));
        $this->getObject(ContentRepositoryRegistry::class)->resetFactoryInstance(static::$contentRepositoryId);
        $this->setupContentRepositoryDependencies(static::$contentRepositoryId);
    }

    final protected function setupContentRepositoryDependencies(ContentRepositoryId $contentRepositoryId)
    {
        $this->contentRepository = $this->getObject(ContentRepositoryRegistry::class)->get(
            $contentRepositoryId
        );

        $subscriptionEngineAndEventStoreAccessor = new class implements ContentRepositoryServiceFactoryInterface {
            public EventStoreInterface|null $eventStore;
            public SubscriptionEngine|null $subscriptionEngine;
            public function build(ContentRepositoryServiceFactoryDependencies $serviceFactoryDependencies): ContentRepositoryServiceInterface
            {
                $this->eventStore = $serviceFactoryDependencies->eventStore;
                $this->subscriptionEngine = $serviceFactoryDependencies->subscriptionEngine;
                return new class implements ContentRepositoryServiceInterface
                {
                };
            }
        };
        $this->getObject(ContentRepositoryRegistry::class)->buildService($contentRepositoryId, $subscriptionEngineAndEventStoreAccessor);
        $this->eventStore = $subscriptionEngineAndEventStoreAccessor->eventStore;
        $this->subscriptionEngine = $subscriptionEngineAndEventStoreAccessor->subscriptionEngine;
    }

    /** @after */
    final public function resetContentRepositoryRegistry(): void
    {
        $originalSettings = $this->getObject(ConfigurationManager::class)->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepositoryRegistry');
        $this->getObject(ContentRepositoryRegistry::class)->injectSettings($originalSettings);
        $this->getObject(ContentRepositoryRegistry::class)->resetFactoryInstance(static::$contentRepositoryId);
    }

    final protected function dropDatabaseSchema(Connection $connection, ContentRepositoryId $contentRepositoryId): void
    {
        $preDeleteStatement = match (true) {
            $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform => 'SET FOREIGN_KEY_CHECKS = 0;',
            default => '',
        };

        if ($preDeleteStatement !== '') {
            $connection->prepare($preDeleteStatement)->executeStatement();
        }

        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $cascade = ' CASCADE';
        } else {
            $cascade = '';
        }

        foreach ($connection->createSchemaManager()->listTableNames() as $tableName) {
            if (!str_starts_with($tableName, sprintf('cr_%s_', $contentRepositoryId->value))) {
                // speedup deletion, only delete current cr
                continue;
            }
            $sql = 'DROP TABLE ' . $connection->quoteIdentifier($tableName) . $cascade;
            $connection->prepare($sql)->executeStatement();
        }

        $postDeleteStatement = match (true) {
            $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform => 'SET FOREIGN_KEY_CHECKS = 1;',
            default => '',
        };

        if ($postDeleteStatement !== '') {
            $connection->prepare($postDeleteStatement)->executeStatement();
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    final protected function getObject(string $className): object
    {
        return Bootstrap::$staticObjectManager->get($className);
    }
}
